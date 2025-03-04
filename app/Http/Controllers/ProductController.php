<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ProductController extends Controller
{
    // index
    public function index()
    {
        $products = Product::paginate(10);
        return view('pages.products.index', compact('products'));
    }

    // create
    public function create()
    {
        $categories = DB::table('categories')->get();
        return view('pages.products.create', compact('categories'));
    }

    // store
    public function store(Request $request)
    {
        // validate the request...
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'price' => 'required|numeric',
            'category_id' => 'required',
            'stock' => 'required|numeric',
            'status' => 'required|boolean',
            'is_favorite' => 'required|boolean',
        ]);

        // store the request...
        $product = new Product;
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->stock = $request->stock;
        $product->status = $request->status;
        $product->is_favorite = $request->is_favorite;

        $product->save();

        //save image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/products', $product->id . '.' . $image->getClientOriginalExtension());
            $product->image = 'storage/products/' . $product->id . '.' . $image->getClientOriginalExtension();
            $product->save();
        }

        return redirect()->route('products.index')->with('success', 'Product created successfully');
    }

    // show
    public function show($id)
    {
        // return view('pages.products.show');
    }

    // edit
    public function edit($id)
    {
        $product = Product::findOrFail($id);
        $categories = DB::table('categories')->get();
        return view('pages.products.edit', compact('product', 'categories'));
    }

    // update
    public function update(Request $request, $id)
    {
        // validate the request...
        $request->validate([
            'name' => 'required',
            'description' => 'required',
            'price' => 'required|numeric',
            'category_id' => 'required',
            'stock' => 'required|numeric',
            'status' => 'required|boolean',
            'is_favorite' => 'required|boolean',
        ]);

        // update the request...
        $product = Product::find($id);
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->category_id = $request->category_id;
        $product->stock = $request->stock;
        $product->status = $request->status;
        $product->is_favorite = $request->is_favorite;
        $product->save();

        //save image
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image->storeAs('public/products', $product->id . '.' . $image->getClientOriginalExtension());
            $product->image = 'storage/products/' . $product->id . '.' . $image->getClientOriginalExtension();
            $product->save();
        }

        return redirect()->route('products.index')->with('success', 'Product updated successfully');
    }

    // destroy
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);

            // Hapus gambar jika ada
            if (!empty($product->image)) {
                $imagePath = public_path($product->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // Hapus data produk
            $product->delete();

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('products.index')->with('error', 'Error deleting product: ' . $e->getMessage());
        }
    }

    /**
     * Apply common styling to a spreadsheet
     */
    private function applyCommonStyles($spreadsheet, $title)
    {
        // Set spreadsheet metadata
        $spreadsheet->getProperties()
            ->setCreator('Seblak Sulthane')
            ->setLastModifiedBy('Seblak Sulthane')
            ->setTitle($title)
            ->setSubject('Seblak Sulthane Products')
            ->setDescription('Generated by Seblak Sulthane Management System');

        // Common header style
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

        return $headerStyle;
    }

    /**
     * Add instructions section to a spreadsheet
     */
    private function addInstructionsSection($sheet, $instructions, $startRow, $column = 'H')
    {
        // Instruction Header Styling
        $instructionHeaderStyle = [
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
        ];

        // Instructions section to the side
        $sheet->setCellValue($column . $startRow, 'INSTRUCTIONS');
        $sheet->getStyle($column . $startRow)->applyFromArray($instructionHeaderStyle);
        $sheet->getRowDimension($startRow)->setRowHeight(30);

        // Instruction content styling
        $instructionContentStyle = [
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
        ];

        // Create a single instruction cell with all instructions
        $instructionText = implode("\n\n", $instructions);
        $sheet->setCellValue($column . ($startRow + 1), $instructionText);
        $sheet->getStyle($column . ($startRow + 1) . ':' . $column . ($startRow + 7))->applyFromArray($instructionContentStyle);
        $sheet->mergeCells($column . ($startRow + 1) . ':' . $column . ($startRow + 7));

        // Set column width for instructions
        $sheet->getColumnDimension($column)->setWidth(50);
    }

    // import
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
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because row 1 is the header and arrays are 0-indexed

                // Skip empty rows (check if name is empty)
                if (empty($row[0])) {
                    continue;
                }

                // Validate category name is not empty
                $categoryName = trim($row[1] ?? '');
                if (empty($categoryName)) {
                    $errors[] = "Row {$rowNumber}: Category name cannot be empty";
                    continue;
                }

                try {
                    // Find category ID by name
                    $category = DB::table('categories')->where('name', $categoryName)->first();

                    if (!$category) {
                        // If category doesn't exist, create it
                        $categoryId = DB::table('categories')->insertGetId([
                            'name' => $categoryName,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } else {
                        $categoryId = $category->id;
                    }

                    // Validate other required fields
                    if (empty($row[0])) {
                        $errors[] = "Row {$rowNumber}: Product name is required";
                        continue;
                    }

                    $price = is_numeric($row[3]) ? $row[3] : 0;
                    $stock = is_numeric($row[4]) ? $row[4] : 0;

                    // Create product
                    Product::create([
                        'name' => $row[0],
                        'category_id' => $categoryId,
                        'description' => $row[2] ?? '',
                        'price' => $price,
                        'stock' => $stock,
                        'status' => 1, // Default active
                        'is_favorite' => 0, // Default not favorite
                    ]);

                    $importCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Successfully imported {$importCount} products.";
            if (!empty($errors)) {
                $message .= " However, there were some errors: " . implode(", ", $errors);
                return redirect()->route('products.index')->with('warning', $message);
            }

            return redirect()
                ->route('products.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('products.index')
                ->with('error', 'Error importing products: ' . $e->getMessage());
        }
    }

    // Improved template generation method with consistent styling
    public function template()
    {
        // Get all categories for the dropdown list
        $categories = DB::table('categories')->pluck('name')->toArray();

        $headers = [
            'Name',
            'Category',
            'Description',
            'Price',
            'Stock'
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Apply common styling
        $headerStyle = $this->applyCommonStyles($spreadsheet, 'Product Import Template');

        // Add headers
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

        // Apply header styling
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Add sample row
        $sampleData = [
            'Seblak Ayam',
            $categories[0] ?? 'Makanan',
            'Seblak dengan topping ayam',
            '15000',
            '50'
        ];

        foreach ($sampleData as $index => $value) {
            $sheet->setCellValue(chr(65 + $index) . '2', $value);
        }

        // Style for sample row
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
        $sheet->getStyle('A2:E2')->applyFromArray($sampleRowStyle);

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

        // Apply styling to empty data rows - increased to 500 rows
        $sheet->getStyle('A3:E500')->applyFromArray($dataRowStyle);

        // Add alternating row colors - increased to 500 rows
        for ($i = 3; $i <= 500; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':E' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // Create dropdown for Category column for all rows - increased to 500 rows
        for ($i = 2; $i <= 500; $i++) {
            $validation = $sheet->getCell('B' . $i)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"' . implode(',', $categories) . '"');
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // Name
        $sheet->getColumnDimension('B')->setWidth(20); // Category
        $sheet->getColumnDimension('C')->setWidth(40); // Description
        $sheet->getColumnDimension('D')->setWidth(15); // Price
        $sheet->getColumnDimension('E')->setWidth(15); // Stock

        // Add instructions section
        $instructions = [
            "1. Fill out product details starting from row 2",
            "2. For Category column, use the dropdown to select a category",
            "3. Don't modify column headers in row 1",
            "4. Price and Stock must be numbers only",
            "5. All products will be set to 'Active' status by default",
            "6. All fields are required except Description",
            "7. Template supports up to 500 product entries"
        ];
        $this->addInstructionsSection($sheet, $instructions, 1, 'G');

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

        $sheet->setCellValue('G10', 'FIELD EXPLANATIONS');
        $sheet->getStyle('G10')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Name' => 'Product name, e.g., "Seblak Ayam"',
            'Category' => 'Product category - choose from dropdown',
            'Description' => 'Detailed description of the product (optional)',
            'Price' => 'Product price in Rupiah (numbers only)',
            'Stock' => 'Initial stock quantity (numbers only)'
        ];

        $fieldRow = 11;
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

        $sheet->getStyle('G11:G15')->applyFromArray($fieldExplanationStyle);

        // Add example section
        $exampleHeaderStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'C65911'] // Orange header
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->setCellValue('G17', 'EXAMPLE ROW');
        $sheet->getStyle('G17')->applyFromArray($exampleHeaderStyle);

        $sheet->setCellValue(
            'G18',
            'See row 2 for an example of a completed product entry'
        );
        $exampleStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FCE4D6'] // Light orange background
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('G18')->applyFromArray($exampleStyle);

        // Set the auto-filter for the data - increased to 500 rows
        $sheet->setAutoFilter('A1:E500');

        // Freeze the header row
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="products_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }

    // Improved export with consistent styling
    public function export()
    {
        try {
            $products = Product::with('category')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Apply common styling
            $headerStyle = $this->applyCommonStyles($spreadsheet, 'Products Export');

            // Set headers
            $headers = [
                'ID',
                'Name',
                'Category',
                'Description',
                'Price',
                'Stock',
                'Status',
                'Is Favorite'
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Apply header styling
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Add data rows
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->id);
                $sheet->setCellValue('B' . $row, $product->name);
                $sheet->setCellValue('C' . $row, $product->category->name);
                $sheet->setCellValue('D' . $row, $product->description);
                $sheet->setCellValue('E' . $row, $product->price);
                $sheet->setCellValue('F' . $row, $product->stock);
                $sheet->setCellValue('G' . $row, $product->status ? 'Active' : 'Inactive');
                $sheet->setCellValue('H' . $row, $product->is_favorite ? 'Yes' : 'No');

                // Format numbers
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');

                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                }

                $row++;
            }

            // Set border for all data
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:H' . ($row - 1))->applyFromArray($borderStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10); // ID
            $sheet->getColumnDimension('B')->setWidth(30); // Name
            $sheet->getColumnDimension('C')->setWidth(20); // Category
            $sheet->getColumnDimension('D')->setWidth(40); // Description
            $sheet->getColumnDimension('E')->setWidth(15); // Price
            $sheet->getColumnDimension('F')->setWidth(15); // Stock
            $sheet->getColumnDimension('G')->setWidth(15); // Status
            $sheet->getColumnDimension('H')->setWidth(15); // Is Favorite

            // Add export info on the right side
            $sheet->setCellValue('J1', 'Exported on: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue('J2', 'Total Products: ' . ($row - 2));

            $exportInfoStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
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
            $sheet->getStyle('J1:J2')->applyFromArray($exportInfoStyle);

            // Set column width for export info
            $sheet->getColumnDimension('J')->setWidth(35);

            // Freeze the header row
            $sheet->freezePane('A2');

            // Set the auto-filter
            $sheet->setAutoFilter('A1:H' . ($row - 1));

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="products_export_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');

            exit();
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Error exporting products: ' . $e->getMessage());
        }
    }

    // Improved bulk update template with consistent styling
    public function exportForUpdate()
    {
        try {
            $products = Product::with('category')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Products Bulk Update Template')
                ->setSubject('Seblak Sulthane Products')
                ->setDescription('Generated by Seblak Sulthane Management System');

            // Get all categories for the dropdown list
            $categories = DB::table('categories')->pluck('name')->toArray();

            // Set headers with status and favorite fields
            $headers = [
                'ID',
                'Name',
                'Category',
                'Description',
                'Price',
                'Stock',
                'Active',
                'Favorite'
            ];

            // Header styling
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Apply header styling
            $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // Data row styling
            $dataRowStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];

            // Add data rows
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->id);
                $sheet->setCellValue('B' . $row, $product->name);
                $sheet->setCellValue('C' . $row, $product->category->name);
                $sheet->setCellValue('D' . $row, $product->description);
                $sheet->setCellValue('E' . $row, $product->price);
                $sheet->setCellValue('F' . $row, $product->stock);

                // Set TRUE/FALSE for status and favorite fields
                $sheet->setCellValue('G' . $row, $product->status ? 'TRUE' : 'FALSE');
                $sheet->setCellValue('H' . $row, $product->is_favorite ? 'TRUE' : 'FALSE');

                // Add data validation for Category column
                $validation = $sheet->getCell('C' . $row)->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . implode(',', $categories) . '"');

                // Add TRUE/FALSE dropdown for Active status column
                $activeValidation = $sheet->getCell('G' . $row)->getDataValidation();
                $activeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $activeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $activeValidation->setAllowBlank(false);
                $activeValidation->setShowDropDown(true);
                $activeValidation->setFormula1('"TRUE,FALSE"');

                // Add TRUE/FALSE dropdown for Favorite column
                $favoriteValidation = $sheet->getCell('H' . $row)->getDataValidation();
                $favoriteValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $favoriteValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $favoriteValidation->setAllowBlank(false);
                $favoriteValidation->setShowDropDown(true);
                $favoriteValidation->setFormula1('"TRUE,FALSE"');

                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                } else {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFFFF');
                }

                $row++;
            }

            // Apply borders to all data cells
            $sheet->getStyle('A1:H' . ($row - 1))->applyFromArray($dataRowStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10); // ID
            $sheet->getColumnDimension('B')->setWidth(30); // Name
            $sheet->getColumnDimension('C')->setWidth(20); // Category
            $sheet->getColumnDimension('D')->setWidth(40); // Description
            $sheet->getColumnDimension('E')->setWidth(15); // Price
            $sheet->getColumnDimension('F')->setWidth(15); // Stock
            $sheet->getColumnDimension('G')->setWidth(15); // Active
            $sheet->getColumnDimension('H')->setWidth(15); // Favorite

            // Add instructions section
            $sheet->setCellValue('J1', 'INSTRUCTIONS');
            $sheet->getStyle('J1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '305496']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // Instruction content
            $instructions = [
                "1. DO NOT modify the ID column (column A)",
                "2. For Category, select from the dropdown list",
                "3. Price and Stock should be numbers only",
                "4. For Active and Favorite columns, select TRUE or FALSE from dropdown",
                "5. Fields you don't want to update can be left unchanged",
                "6. The system will only update the fields you modify",
                "7. Do not modify the header row",
                "8. When finished, save and upload the file to perform the updates"
            ];

            $instructionText = implode("\n\n", $instructions);
            $sheet->setCellValue('J2', $instructionText);
            $sheet->getStyle('J2:J9')->applyFromArray([
                'font' => [
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9E1F2'] // Light blue background
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ],
            ]);
            $sheet->mergeCells('J2:J9');
            $sheet->getColumnDimension('J')->setWidth(50);

            // Set freeze pane on first row and first column
            $sheet->freezePane('B2');

            // Set the auto-filter
            $sheet->setAutoFilter('A1:H' . ($row - 1));

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="products_for_update.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');

            exit();
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Error exporting products: ' . $e->getMessage());
        }
    }

    // Improved bulk update method
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
            $skippedCount = 0;

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    $product = Product::where('id', $row[0])->first();

                    if (!$product) {
                        $errors[] = "Row " . ($index + 2) . ": Product with ID {$row[0]} not found";
                        continue;
                    }

                    // Find category ID by name if provided and changed
                    $categoryId = $product->category_id;
                    if (!empty($row[2]) && $row[2] !== $product->category->name) {
                        $category = DB::table('categories')->where('name', $row[2])->first();
                        if ($category) {
                            $categoryId = $category->id;
                        } else {
                            // Create new category if it doesn't exist
                            $categoryId = DB::table('categories')->insertGetId([
                                'name' => $row[2],
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }

                    // Update only if fields have been modified
                    $updates = [];
                    $hasChanges = false;

                    // Check and update name if changed
                    if (!empty($row[1]) && $row[1] !== $product->name) {
                        $updates['name'] = $row[1];
                        $hasChanges = true;
                    }

                    // Check and update category if changed
                    if ($categoryId !== $product->category_id) {
                        $updates['category_id'] = $categoryId;
                        $hasChanges = true;
                    }

                    // Check and update description if changed
                    if (isset($row[3]) && $row[3] !== $product->description) {
                        $updates['description'] = $row[3];
                        $hasChanges = true;
                    }

                    // Check and update price if changed and valid
                    if (isset($row[4]) && is_numeric($row[4]) && (float)$row[4] !== (float)$product->price) {
                        $updates['price'] = $row[4];
                        $hasChanges = true;
                    }

                    // Check and update stock if changed and valid
                    if (isset($row[5]) && is_numeric($row[5]) && (int)$row[5] !== (int)$product->stock) {
                        $updates['stock'] = $row[5];
                        $hasChanges = true;
                    }

                    // Only update if there are changes
                    if ($hasChanges) {
                        $product->update($updates);
                        $updateCount++;
                        \Log::info("Updated product ID {$product->id}: " . json_encode($updates));
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                    \Log::error("Error processing row " . ($index + 2) . ": " . $e->getMessage());
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "Product update summary: {$updateCount} products updated";
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} products unchanged";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= ". However, there were some errors: " . implode("; ", $errors);
                    return redirect()->route('products.index')->with('warning', $message);
                } else {
                    $message .= ". However, there were " . count($errors) . " errors. First few: " .
                        implode(
                            "; ",
                            array_slice($errors, 0, 3)
                        ) . "...";
                    return redirect()->route('products.index')->with('warning', $message);
                }
            }

            return redirect()->route('products.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('products.index')
                ->with('error', 'Error updating products: ' . $e->getMessage());
        }
    }

    public function deleteAll()
    {
        \Log::info('deleteAll method called');

        try {
            // Mulai transaksi
            DB::beginTransaction();
            \Log::info('Starting delete all process');

            // Hapus gambar terlebih dahulu
            $products = Product::whereNotNull('image')->get();
            foreach ($products as $product) {
                if ($product->image) {
                    $imagePath = public_path($product->image);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
            }

            // Get count for reporting
            $productCount = Product::count();

            // Nonaktifkan foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Hapus semua produk
            Product::query()->delete();

            // Aktifkan kembali foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Commit transaksi
            DB::commit();
            \Log::info("Successfully deleted all {$productCount} products");

            return redirect()
                ->route('products.index')
                ->with('success', "All {$productCount} products have been deleted successfully");
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error in deleteAll: ' . $e->getMessage());

            // Rollback hanya jika transaksi masih aktif
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Pastikan foreign key checks diaktifkan kembali
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return redirect()
                ->route('products.index')
                ->with('error', 'Error deleting products: ' . $e->getMessage());
        }
    }
}
