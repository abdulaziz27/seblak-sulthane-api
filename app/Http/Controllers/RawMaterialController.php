<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RawMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = RawMaterial::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $request->status);
        }

        // Sort functionality
        if ($request->has('sort_by')) {
            $sortField = $request->sort_by;
            $sortDirection = $request->input('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest();
        }

        $materials = $query->paginate(10);

        return view('pages.raw-materials.index', compact('materials'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.raw-materials.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        RawMaterial::create($request->all());

        return redirect()->route('raw-materials.index')
            ->with('success', 'Raw material created successfully');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RawMaterial $rawMaterial)
    {
        return view('pages.raw-materials.edit', compact('rawMaterial'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RawMaterial $rawMaterial)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $rawMaterial->update($request->all());

        return redirect()->route('raw-materials.index')
            ->with('success', 'Raw material updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RawMaterial $rawMaterial)
    {
        try {
            $rawMaterial->delete();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Raw material deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('raw-materials.index')
                ->with('error', 'Failed to delete raw material. It may be in use.');
        }
    }

    /**
     * Update stock levels
     */
    public function updateStock(Request $request, RawMaterial $rawMaterial)
    {
        $request->validate([
            'adjustment' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $newStock = $rawMaterial->stock + $request->adjustment;

            if ($newStock < 0) {
                return redirect()->back()
                    ->with('error', 'Stock cannot be negative.');
            }

            $rawMaterial->update([
                'stock' => $newStock
            ]);

            // Create stock movement record (implementation optional)

            DB::commit();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Stock updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update stock: ' . $e->getMessage());
        }
    }

    /**
     * Import raw materials from Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        try {
            DB::beginTransaction();

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $spreadsheet = $reader->load($file);
            $rows = $spreadsheet->getActiveSheet()->toArray();

            // Skip header row
            array_shift($rows);

            $importCount = 0;
            $updateCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because row 1 is header and arrays are 0-indexed

                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    // Validate essential fields
                    if (empty($row[0])) {
                        $errors[] = "Row {$rowNumber}: Material name is required";
                        continue;
                    }

                    if (empty($row[1])) {
                        $errors[] = "Row {$rowNumber}: Unit is required";
                        continue;
                    }

                    if (!is_numeric($row[2])) {
                        $errors[] = "Row {$rowNumber}: Price must be a number";
                        continue;
                    }

                    // Prepare data
                    $materialData = [
                        'name' => trim($row[0]),
                        'unit' => trim($row[1]),
                        'price' => (int)$row[2],
                        'stock' => is_numeric($row[3]) ? (int)$row[3] : 0,
                        'description' => $row[4] ?? null,
                        'is_active' => isset($row[5]) ? (strtoupper($row[5]) === 'TRUE' ? 1 : 0) : 1
                    ];

                    // Check if material already exists (by exact name match)
                    $existingMaterial = RawMaterial::where('name', $materialData['name'])->first();

                    if ($existingMaterial) {
                        // Update existing material
                        $existingMaterial->update($materialData);
                        $updateCount++;
                    } else {
                        // Create new material
                        RawMaterial::create($materialData);
                        $importCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "";

            if ($importCount > 0) {
                $message .= "{$importCount} new materials imported. ";
            }

            if ($updateCount > 0) {
                $message .= "{$updateCount} existing materials updated. ";
            }

            if ($importCount === 0 && $updateCount === 0) {
                $message = "No materials were imported or updated. ";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= "However, there were some errors: " . implode("; ", $errors);
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                } else {
                    $message .= "However, there were " . count($errors) . " errors. First few: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                }
            }

            return redirect()->route('raw-materials.index')
                ->with('success', trim($message));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('raw-materials.index')
                ->with('error', 'Error importing raw materials: ' . $e->getMessage());
        }
    }

    /**
     * Generate import template with consistent styling
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set spreadsheet metadata
        $spreadsheet->getProperties()
            ->setCreator('Seblak Sulthane')
            ->setLastModifiedBy('Seblak Sulthane')
            ->setTitle('Raw Materials Import Template')
            ->setSubject('Template for Raw Materials Import')
            ->setDescription('Generated by Seblak Sulthane Management System');

        // Add headers
        $headers = ['Name', 'Unit', 'Price', 'Stock', 'Description', 'Is Active'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

        // Header styling
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Add sample row
        $sampleData = [
            'Bawang Merah',
            'Kg',
            '25000',
            '100',
            'Bawang merah segar',
            'TRUE'
        ];
        foreach ($sampleData as $index => $value) {
            $sheet->setCellValue(chr(65 + $index) . '2', $value);
        }

        // Sample row styling
        $sampleRowStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A2:F2')->applyFromArray($sampleRowStyle);

        // Create dropdown for Active column
        $validation = $sheet->getCell('F2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"TRUE,FALSE"');

        // Create dropdowns for unit column
        $units = ['Kg', 'Ball', 'Bks', 'Ikat', 'Pcs', 'Dus', 'Pack', 'Renteng', 'Botol', 'Slop', 'Box', 'Peti'];
        $unitValidation = $sheet->getCell('B2')->getDataValidation();
        $unitValidation->setType(DataValidation::TYPE_LIST);
        $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $unitValidation->setAllowBlank(false);
        $unitValidation->setShowDropDown(true);
        $unitValidation->setFormula1('"' . implode(',', $units) . '"');

        // Style for empty data rows - increased to 100 rows for plenty of space
        $dataRowStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F9F9F9']
            ],
        ];

        // Apply styling to empty data rows
        $sheet->getStyle('A2:F2')->applyFromArray($sampleRowStyle);

        // Style for empty data rows - increased to 300 rows
        $dataRowStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F9F9F9']
            ],
        ];
        $sheet->getStyle('A3:F300')->applyFromArray($dataRowStyle);

        // Add alternating row colors for empty data rows
        for ($i = 3; $i <= 300; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':F' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // Create dropdown for all Active columns
        for ($i = 3; $i <= 300; $i++) {
            $validation = $sheet->getCell('F' . $i)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"TRUE,FALSE"');

            // Create dropdown for unit column
            $unitValidation = $sheet->getCell('B' . $i)->getDataValidation();
            $unitValidation->setType(DataValidation::TYPE_LIST);
            $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $unitValidation->setAllowBlank(false);
            $unitValidation->setShowDropDown(true);
            $unitValidation->setFormula1('"Kg,Ball,Bks,Ikat,Pcs,Dus,Pack,Renteng,Botol,Slop,Box,Peti"');
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // Name
        $sheet->getColumnDimension('B')->setWidth(15); // Unit
        $sheet->getColumnDimension('C')->setWidth(15); // Price
        $sheet->getColumnDimension('D')->setWidth(15); // Stock
        $sheet->getColumnDimension('E')->setWidth(40); // Description
        $sheet->getColumnDimension('F')->setWidth(15); // Is Active

        // Add instructions section
        $instructions = [
            "1. Fill out raw material details starting from row 2",
            "2. For Unit column, use the dropdown to select a unit type",
            "3. For Is Active column, select TRUE or FALSE from dropdown",
            "4. Price and Stock must be numbers only",
            "5. All fields are required except Description",
            "6. Template supports up to 100 raw material entries"
        ];

        // Add instructions section
        $sheet->setCellValue('H1', 'INSTRUCTIONS');
        $sheet->getStyle('H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '305496']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Instruction content
        $instructionText = implode("\n\n", $instructions);
        $sheet->setCellValue('H2', $instructionText);
        $sheet->getStyle('H2:H7')->applyFromArray([
            'font' => [
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'] // Light blue background
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true
            ],
        ]);
        $sheet->mergeCells('H2:H7');
        $sheet->getColumnDimension('H')->setWidth(50);

        // Add field explanations
        $fieldExplanationHeaderStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '548235'] // Green header
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->setCellValue('H9', 'FIELD EXPLANATIONS');
        $sheet->getStyle('H9')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Name' => 'Raw material name, e.g., "Bawang Merah"',
            'Unit' => 'Unit of measurement - choose from dropdown',
            'Price' => 'Price per unit in Rupiah (numbers only)',
            'Stock' => 'Initial stock quantity (numbers only)',
            'Description' => 'Additional information (optional)',
            'Is Active' => 'Material status (TRUE = active, FALSE = inactive)'
        ];

        $fieldRow = 10;
        foreach ($fieldExplanations as $field => $explanation) {
            $sheet->setCellValue('H' . $fieldRow, "$field: $explanation");
            $fieldRow++;
        }

        $fieldExplanationStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA'] // Light green background
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
        ];

        $sheet->getStyle('H10:H15')->applyFromArray($fieldExplanationStyle);

        // Set the auto-filter for the data
        $sheet->setAutoFilter('A1:F300');

        // Freeze the header row and first column
        $sheet->freezePane('B2');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="raw_materials_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit();
    }

    /**
     * Export raw materials to Excel with consistent styling
     */
    public function export()
    {
        try {
            $materials = RawMaterial::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Raw Materials Export')
                ->setSubject('Raw Materials Data')
                ->setDescription('Generated by Seblak Sulthane Management System');

            // Add headers
            $headers = ['ID', 'Name', 'Unit', 'Price', 'Stock', 'Total Value', 'Description', 'Status'];
            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Header styling
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Data rows
            $row = 2;
            $totalInventoryValue = 0;

            foreach ($materials as $material) {
                $itemValue = $material->stock * $material->price;
                $totalInventoryValue += $itemValue;

                $sheet->setCellValue('A' . $row, $material->id);
                $sheet->setCellValue('B' . $row, $material->name);
                $sheet->setCellValue('C' . $row, $material->unit);
                $sheet->setCellValue('D' . $row, $material->price);
                $sheet->setCellValue('E' . $row, $material->stock);
                $sheet->setCellValue('F' . $row, $itemValue);
                $sheet->setCellValue('G' . $row, $material->description);
                $sheet->setCellValue('H' . $row, $material->is_active ? 'Active' : 'Inactive');

                // Format numbers
                $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0');

                // Format low stock items
                if ($material->stock < 10) {
                    $sheet->getStyle('E' . $row)
                        ->getFont()
                        ->getColor()
                        ->setARGB('FF0000'); // Red color for low stock
                }

                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                }

                $row++;
            }

            // Add total row
            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->setCellValue('F' . $row, $totalInventoryValue);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDEBF7'); // Light blue

            // Set border for all data
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:H' . $row)->applyFromArray($borderStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10);  // ID
            $sheet->getColumnDimension('B')->setWidth(30);  // Name
            $sheet->getColumnDimension('C')->setWidth(15);  // Unit
            $sheet->getColumnDimension('D')->setWidth(15);  // Price
            $sheet->getColumnDimension('E')->setWidth(15);  // Stock
            $sheet->getColumnDimension('F')->setWidth(20);  // Total Value
            $sheet->getColumnDimension('G')->setWidth(40);  // Description
            $sheet->getColumnDimension('H')->setWidth(15);  // Status

            // Add export info on the right side
            $sheet->setCellValue('J1', 'Export Information');
            $sheet->getStyle('J1')->getFont()->setBold(true);
            $sheet->getStyle('J1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle('J1')->getFont()->getColor()->setARGB('FFFFFF');
            $sheet->getStyle('J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('J2', 'Generated on: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue('J3', 'Total Materials: ' . ($row - 2));
            $sheet->setCellValue('J4', 'Total Inventory Value: Rp ' . number_format($totalInventoryValue, 0, ',', '.'));
            $sheet->setCellValue('J5', 'Low Stock Items: ' . $materials->where('stock', '<', 10)->count());

            $exportInfoStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DDEBF7'] // Light blue background
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('J2:J5')->applyFromArray($exportInfoStyle);
            $sheet->getStyle('J5')->getFont()->getColor()->setARGB('FF0000'); // Red color for low stock warning
            $sheet->getColumnDimension('J')->setWidth(40);

            // Set the auto-filter
            $sheet->setAutoFilter('A1:H' . ($row - 1));

            // Freeze the header row and ID column
            $sheet->freezePane('B2');

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="raw_materials_export_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('raw-materials.index')
                ->with('error', 'Error exporting raw materials: ' . $e->getMessage());
        }
    }
}
