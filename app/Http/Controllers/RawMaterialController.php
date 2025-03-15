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

        // Get total counts for stats
        $totalCount = RawMaterial::count();
        $activeCount = RawMaterial::where('is_active', 1)->count();
        $lowStockCount = RawMaterial::where('stock', '<', 10)->count();
        $totalValue = RawMaterial::selectRaw('SUM(stock * price) as total_value')->first()->total_value ?? 0;

        $materials = $query->paginate(10);

        return view('pages.raw-materials.index', compact(
            'materials',
            'totalCount',
            'activeCount',
            'lowStockCount',
            'totalValue'
        ));
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
            ->with('success', 'Bahan baku berhasil ditambahkan');
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
            ->with('success', 'Bahan baku berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RawMaterial $rawMaterial)
    {
        try {
            $rawMaterial->delete();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Bahan baku berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->route('raw-materials.index')
                ->with('error', 'Gagal menghapus bahan baku. Mungkin sedang digunakan.');
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
                    ->with('error', 'Stok tidak boleh negatif.');
            }

            $rawMaterial->update([
                'stock' => $newStock
            ]);

            // Create stock movement record (implementation optional)

            DB::commit();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Stok berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal memperbarui stok: ' . $e->getMessage());
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
                        $errors[] = "Baris {$rowNumber}: Nama bahan baku wajib diisi";
                        continue;
                    }

                    if (empty($row[1])) {
                        $errors[] = "Baris {$rowNumber}: Satuan wajib diisi";
                        continue;
                    }

                    if (!is_numeric($row[2])) {
                        $errors[] = "Baris {$rowNumber}: Harga harus berupa angka";
                        continue;
                    }

                    // Prepare data
                    $materialData = [
                        'name' => trim($row[0]),
                        'unit' => trim($row[1]),
                        'price' => (int)$row[2],
                        'stock' => is_numeric($row[3]) ? (int)$row[3] : 0,
                        'description' => $row[4] ?? null,
                        'is_active' => 1  // Always set to active by default
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
                    $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "";

            if ($importCount > 0) {
                $message .= "{$importCount} bahan baku baru berhasil diimpor. ";
            }

            if ($updateCount > 0) {
                $message .= "{$updateCount} bahan baku yang sudah ada berhasil diperbarui. ";
            }

            if ($importCount === 0 && $updateCount === 0) {
                $message = "Tidak ada bahan baku yang diimpor atau diperbarui. ";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= "Namun, terdapat beberapa kesalahan: " . implode("; ", $errors);
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                } else {
                    $message .= "Namun, terdapat " . count($errors) . " kesalahan. Beberapa diantaranya: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                }
            }

            return redirect()->route('raw-materials.index')
                ->with('success', trim($message));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan mengimpor bahan baku: ' . $e->getMessage());
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
            ->setTitle('Template Impor Bahan Baku')
            ->setSubject('Template untuk Impor Bahan Baku')
            ->setDescription('Dibuat oleh Sistem Manajemen Seblak Sulthane');

        // Add headers - REMOVED Status Aktif column
        $headers = ['Nama', 'Satuan', 'Harga', 'Stok', 'Deskripsi'];
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
        // Update styling range to E instead of F
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Add sample row - REMOVED Status Aktif value
        $sampleData = [
            'Bawang Merah',
            'Kg',
            '25000',
            '100',
            'Bawang merah segar'
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
        // Update styling range to E instead of F
        $sheet->getStyle('A2:E2')->applyFromArray($sampleRowStyle);

        // Create dropdowns for unit column
        $units = ['Kg', 'Ball', 'Bks', 'Ikat', 'Pcs', 'Dus', 'Pack', 'Renteng', 'Botol', 'Slop', 'Box', 'Peti'];
        $unitValidation = $sheet->getCell('B2')->getDataValidation();
        $unitValidation->setType(DataValidation::TYPE_LIST);
        $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $unitValidation->setAllowBlank(false);
        $unitValidation->setShowDropDown(true);
        $unitValidation->setFormula1('"' . implode(',', $units) . '"');

        // Style for empty data rows
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
        // Update styling range to E instead of F
        $sheet->getStyle('A3:E300')->applyFromArray($dataRowStyle);

        // Add alternating row colors for empty data rows
        for ($i = 3; $i <= 300; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':E' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // Create dropdown for unit column for all rows
        for ($i = 3; $i <= 300; $i++) {
            // Create dropdown for unit column
            $unitValidation = $sheet->getCell('B' . $i)->getDataValidation();
            $unitValidation->setType(DataValidation::TYPE_LIST);
            $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $unitValidation->setAllowBlank(false);
            $unitValidation->setShowDropDown(true);
            $unitValidation->setFormula1('"Kg,Ball,Bks,Ikat,Pcs,Dus,Pack,Renteng,Botol,Slop,Box,Peti"');
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // Nama
        $sheet->getColumnDimension('B')->setWidth(15); // Satuan
        $sheet->getColumnDimension('C')->setWidth(15); // Harga
        $sheet->getColumnDimension('D')->setWidth(15); // Stok
        $sheet->getColumnDimension('E')->setWidth(40); // Deskripsi

        // Add instructions section
        $instructions = [
            "1. Isi detail bahan baku mulai dari baris 2",
            "2. Untuk kolom Satuan, gunakan dropdown untuk memilih jenis satuan",
            "3. Harga dan Stok harus berupa angka",
            "4. Semua kolom wajib diisi kecuali Deskripsi",
            "5. Template mendukung hingga 300 baris bahan baku",
            "6. Status Aktif akan otomatis diatur sebagai aktif"
        ];

        // Add instructions section
        $sheet->setCellValue('G1', 'PETUNJUK PENGISIAN');
        $sheet->getStyle('G1')->applyFromArray([
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
        $sheet->setCellValue('G2', $instructionText);
        $sheet->getStyle('G2:G7')->applyFromArray([
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
        $sheet->mergeCells('G2:G7');
        $sheet->getColumnDimension('G')->setWidth(50);

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

        $sheet->setCellValue('G9', 'PENJELASAN KOLOM');
        $sheet->getStyle('G9')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Nama' => 'Nama bahan baku, contoh: "Bawang Merah"',
            'Satuan' => 'Satuan ukuran - pilih dari dropdown',
            'Harga' => 'Harga per satuan dalam Rupiah (angka saja)',
            'Stok' => 'Jumlah stok awal (angka saja)',
            'Deskripsi' => 'Informasi tambahan (opsional)'
        ];

        $fieldRow = 10;
        foreach ($fieldExplanations as $field => $explanation) {
            $sheet->setCellValue('G' . $fieldRow, "$field: $explanation");
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

        $sheet->getStyle('G10:G14')->applyFromArray($fieldExplanationStyle);

        // Set the auto-filter for the data - Updated to E instead of F
        $sheet->setAutoFilter('A1:E300');

        // Freeze the header row and first column
        $sheet->freezePane('B2');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_bahan_baku.xlsx"');
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
                ->setTitle('Ekspor Bahan Baku')
                ->setSubject('Data Bahan Baku')
                ->setDescription('Dibuat oleh Sistem Manajemen Seblak Sulthane');

            // Add headers
            $headers = ['ID', 'Nama', 'Satuan', 'Harga', 'Stok', 'Nilai Total', 'Deskripsi', 'Status'];
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
                $sheet->setCellValue('H' . $row, $material->is_active ? 'Aktif' : 'Tidak Aktif');

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
            $sheet->getColumnDimension('B')->setWidth(30);  // Nama
            $sheet->getColumnDimension('C')->setWidth(15);  // Satuan
            $sheet->getColumnDimension('D')->setWidth(15);  // Harga
            $sheet->getColumnDimension('E')->setWidth(15);  // Stok
            $sheet->getColumnDimension('F')->setWidth(20);  // Total Value
            $sheet->getColumnDimension('G')->setWidth(40);  // Deskripsi
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
                ->with('error', 'Kesalahan mengekspor bahan baku: ' . $e->getMessage());
        }
    }

    /**
     * Generate template for bulk updating materials with consistent styling
     */
    public function exportForUpdate()
    {
        try {
            $materials = RawMaterial::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Template Update Massal Bahan Baku')
                ->setSubject('Update Massal Bahan Baku')
                ->setDescription('Dibuat oleh Sistem Manajemen Seblak Sulthane');

            // Add headers
            $headers = ['ID', 'Nama', 'Satuan', 'Harga', 'Stok', 'Deskripsi', 'Status Aktif'];
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
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Data rows
            $row = 2;
            foreach ($materials as $material) {
                $sheet->setCellValue('A' . $row, $material->id);
                $sheet->setCellValue('B' . $row, $material->name);
                $sheet->setCellValue('C' . $row, $material->unit);
                $sheet->setCellValue('D' . $row, $material->price);
                $sheet->setCellValue('E' . $row, $material->stock);
                $sheet->setCellValue('F' . $row, $material->description);
                $sheet->setCellValue('G' . $row, $material->is_active ? 'TRUE' : 'FALSE');

                // Protect the ID cell from editing
                $sheet->getStyle('A' . $row)->getProtection()
                    ->setLocked(true);

                // Highlight ID column to indicate it shouldn't be modified
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9D9D9'); // Light gray background

                // Create dropdown for Active column
                $validation = $sheet->getCell('G' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"TRUE,FALSE"');

                // Create dropdown for unit column
                $unitValidation = $sheet->getCell('C' . $row)->getDataValidation();
                $unitValidation->setType(DataValidation::TYPE_LIST);
                $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $unitValidation->setAllowBlank(false);
                $unitValidation->setShowDropDown(true);
                $unitValidation->setFormula1('"Kg,Ball,Bks,Ikat,Pcs,Dus,Pack,Renteng,Botol,Slop,Box,Peti"');

                // Alternate row colors for data rows
                if ($row % 2 == 0) {
                    $sheet->getStyle('B' . $row . ':G' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                } else {
                    $sheet->getStyle('B' . $row . ':G' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFFFF');
                }

                $row++;
            }

            // Add warning about ID column
            $extraRow = $row + 1;
            $sheet->setCellValue('A' . $extraRow, 'PERHATIAN: Jangan mengubah kolom ID karena akan digunakan sebagai referensi.');
            $sheet->mergeCells('A' . $extraRow . ':G' . $extraRow);
            $sheet->getStyle('A' . $extraRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $extraRow)->getFont()->getColor()->setARGB('FF0000'); // Red color
            $sheet->getStyle('A' . $extraRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFE6E6'); // Light red background

            // Format the entire data area
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:G' . ($row - 1))->applyFromArray($borderStyle);

            // Add instructions section
            $sheet->setCellValue('I1', 'PETUNJUK PENGISIAN');
            $sheet->getStyle('I1')->applyFromArray([
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

            // Instruction content
            $instructions = [
                "1. JANGAN mengubah nilai pada kolom ID (berwarna abu-abu)",
                "2. Kolom ID digunakan sebagai referensi untuk mengidentifikasi bahan baku",
                "3. Update data dengan mengubah nilai pada kolom yang ingin diperbarui",
                "4. Kolom Nama dan Satuan wajib diisi",
                "5. Untuk Satuan dan Status Aktif, gunakan dropdown untuk memilih",
                "6. Setelah selesai mengupdate, simpan file dan upload kembali"
            ];

            $instructionText = implode("\n\n", $instructions);
            $sheet->setCellValue('I2', $instructionText);
            $sheet->getStyle('I2:I7')->applyFromArray([
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
            $sheet->mergeCells('I2:I7');
            $sheet->getColumnDimension('I')->setWidth(50);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10);  // ID
            $sheet->getColumnDimension('B')->setWidth(30);  // Nama
            $sheet->getColumnDimension('C')->setWidth(15);  // Satuan
            $sheet->getColumnDimension('D')->setWidth(15);  // Harga
            $sheet->getColumnDimension('E')->setWidth(15);  // Stok
            $sheet->getColumnDimension('F')->setWidth(40);  // Deskripsi
            $sheet->getColumnDimension('G')->setWidth(15);  // Status Aktif

            // Set the auto-filter
            $sheet->setAutoFilter('A1:G' . ($row - 1));

            // Freeze panes (first row and ID column)
            $sheet->freezePane('B2');

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="update_bahan_baku_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan menyiapkan template update: ' . $e->getMessage());
        }
    }

    /**
     * Bulk update raw materials from Excel file
     */
    public function bulkUpdate(Request $request)
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

            $updateCount = 0;
            $errors = [];
            $unchangedCount = 0;

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because row 1 is header and arrays are 0-indexed

                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    // Find material by ID
                    $material = RawMaterial::find($row[0]);

                    if (!$material) {
                        $errors[] = "Baris {$rowNumber}: Bahan baku dengan ID {$row[0]} tidak ditemukan";
                        continue;
                    }

                    // Validate essential fields
                    if (empty($row[1])) {
                        $errors[] = "Baris {$rowNumber}: Nama bahan baku wajib diisi";
                        continue;
                    }

                    if (empty($row[2])) {
                        $errors[] = "Baris {$rowNumber}: Satuan wajib diisi";
                        continue;
                    }

                    // Prepare data
                    $updates = [];
                    $hasChanges = false;

                    // Check each field for changes
                    if ($row[1] !== $material->name) {
                        $updates['name'] = trim($row[1]);
                        $hasChanges = true;
                    }

                    if ($row[2] !== $material->unit) {
                        $updates['unit'] = trim($row[2]);
                        $hasChanges = true;
                    }

                    // Check and update price if changed and valid
                    if (isset($row[3]) && is_numeric($row[3]) && (float)$row[3] !== (float)$material->price) {
                        $updates['price'] = (float)$row[3];
                        $hasChanges = true;
                    }

                    // Check and update stock if changed and valid
                    if (isset($row[4]) && is_numeric($row[4]) && (int)$row[4] !== (int)$material->stock) {
                        $updates['stock'] = (int)$row[4];
                        $hasChanges = true;
                    }

                    // Check and update description if changed
                    if (isset($row[5]) && $row[5] !== $material->description) {
                        $updates['description'] = $row[5];
                        $hasChanges = true;
                    }

                    // Check and update active status if changed
                    if (isset($row[6])) {
                        $newStatus = strtoupper($row[6]) === 'TRUE' ? 1 : 0;
                        if ($newStatus !== $material->is_active) {
                            $updates['is_active'] = $newStatus;
                            $hasChanges = true;
                        }
                    }

                    // Only update if there are changes
                    if ($hasChanges) {
                        $material->update($updates);
                        $updateCount++;
                    } else {
                        $unchangedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "{$updateCount} bahan baku berhasil diperbarui.";

            if ($unchangedCount > 0) {
                $message .= " {$unchangedCount} bahan baku tidak ada perubahan.";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= " Namun, terdapat beberapa kesalahan: " . implode("; ", $errors);
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                } else {
                    $message .= " Namun, terdapat " . count($errors) . " kesalahan. Beberapa diantaranya: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('raw-materials.index')->with('warning', $message);
                }
            }

            return redirect()->route('raw-materials.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan memperbarui bahan baku: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete all raw materials without relation checking
     */
    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            // Track how many materials will be deleted
            $materialCount = RawMaterial::count();

            if ($materialCount === 0) {
                DB::rollBack();
                return redirect()->route('raw-materials.index')
                    ->with('info', 'Tidak ada bahan baku yang ditemukan untuk dihapus.');
            }

            // Soft delete all materials without checking relations
            RawMaterial::query()->delete();

            // Commit the transaction
            DB::commit();

            // Log successful deletion
            \Log::info("Berhasil melakukan soft delete pada {$materialCount} bahan baku");

            return redirect()->route('raw-materials.index')
                ->with('success', "Semua {$materialCount} bahan baku berhasil dihapus.");
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error dalam deleteAll bahan baku: ' . $e->getMessage());

            // Rollback transaction if still active
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan menghapus bahan baku: ' . $e->getMessage());
        }
    }
}
