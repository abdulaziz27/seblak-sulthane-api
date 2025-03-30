<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
use Illuminate\Support\Facades\Log;
use App\Models\OrderItem;

class ProductController extends Controller
{
    // index
    public function index(Request $request)
    {
        // Buat query dasar dengan eager loading kategori
        $query = Product::with('category');

        // Filter berdasarkan nama produk
        if ($request->has('name') && !empty($request->name)) {
            $search = $request->name;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting default berdasarkan tanggal pembuatan
        $query->orderBy('created_at', 'desc');

        // Pagination dengan mempertahankan query string
        $products = $query->paginate(50)->withQueryString();

        return view('pages.products.index', compact('products'));
    }

    // create
    public function create()
    {
        // $categories = DB::table('categories')->get();
        $categories = Category::withoutTrashed()->get();
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

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan');
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
        // $categories = DB::table('categories')->get();
        $categories = Category::withoutTrashed()->get();
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

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui');
    }

    // destroy
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($id);

            // Hapus gambar produk jika ada (opsional)
            if (!empty($product->image)) {
                $imagePath = public_path($product->image);
                if (file_exists($imagePath)) {
                    try {
                        unlink($imagePath);
                    } catch (\Exception $e) {
                        \Log::warning("Gagal menghapus gambar produk: " . $e->getMessage());
                    }
                }
            }

            // Soft delete produk
            $product->delete();

            DB::commit();
            return redirect()->route('products.index')
                ->with('success', "Produk '{$product->name}' berhasil diarsipkan.");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal menghapus produk: ' . $e->getMessage());

            return redirect()->route('products.index')
                ->with('error', "Gagal menghapus produk: {$e->getMessage()}");
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
        $sheet->setCellValue($column . $startRow, 'PETUNJUK');
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
                    $errors[] = "Baris {$rowNumber}: Nama kategori tidak boleh kosong";
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
                        $errors[] = "Baris {$rowNumber}: Nama produk wajib diisi";
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
                    $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Berhasil mengimpor {$importCount} produk.";
            if (!empty($errors)) {
                $message .= " Namun, ada beberapa kesalahan: " . implode(", ", $errors);
                return redirect()->route('products.index')->with('warning', $message);
            }

            return redirect()
                ->route('products.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('products.index')
                ->with('error', 'Gagal mengimpor produk: ' . $e->getMessage());
        }
    }

    // Improved template generation method with consistent styling
    public function template()
    {
        // Get all categories for the dropdown list
        $categories = DB::table('categories')->pluck('name')->toArray();

        $headers = [
            'Nama',
            'Kategori',
            'Deskripsi',
            'Harga',
            'Stok'
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Apply common styling
        $headerStyle = $this->applyCommonStyles($spreadsheet, 'Template Import Produk');

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
        $sheet->getColumnDimension('A')->setWidth(30); // Nama
        $sheet->getColumnDimension('B')->setWidth(20); // Kategori
        $sheet->getColumnDimension('C')->setWidth(40); // Deskripsi
        $sheet->getColumnDimension('D')->setWidth(15); // Harga
        $sheet->getColumnDimension('E')->setWidth(15); // Stok

        // Add instructions section
        $instructions = [
            "1. Isi data produk mulai dari baris ke-2",
            "2. Untuk kolom Kategori, gunakan dropdown untuk memilih kategori",
            "3. Jangan mengubah header kolom di baris 1",
            "4. Harga dan Stok harus berupa angka",
            "5. Semua produk akan diatur ke status 'Aktif' secara default",
            "6. Semua kolom wajib diisi kecuali Deskripsi",
            "7. Template ini mendukung hingga 500 entri produk"
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

        $sheet->setCellValue('G10', 'PENJELASAN KOLOM');
        $sheet->getStyle('G10')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Nama' => 'Nama produk, contoh: "Seblak Ayam"',
            'Kategori' => 'Kategori produk - pilih dari dropdown',
            'Deskripsi' => 'Deskripsi detail dari produk (opsional)',
            'Harga' => 'Harga produk dalam Rupiah (angka saja)',
            'Stok' => 'Jumlah stok awal (angka saja)'
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

        $sheet->setCellValue('G17', 'CONTOH BARIS');
        $sheet->getStyle('G17')->applyFromArray($exampleHeaderStyle);

        $sheet->setCellValue(
            'G18',
            'Lihat baris 2 untuk contoh pengisian produk yang lengkap'
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
        header('Content-Disposition: attachment;filename="template_produk.xlsx"');
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
            $headerStyle = $this->applyCommonStyles($spreadsheet, 'Export Produk');

            // Set headers
            $headers = [
                'ID',
                'Nama',
                'Kategori',
                'Deskripsi',
                'Harga',
                'Stok',
                'Status',
                'Favorit'
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
                $sheet->setCellValue('G' . $row, $product->status ? 'Aktif' : 'Tidak Aktif');
                $sheet->setCellValue('H' . $row, $product->is_favorite ? 'Ya' : 'Tidak');

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
            $sheet->getColumnDimension('B')->setWidth(30); // Nama
            $sheet->getColumnDimension('C')->setWidth(20); // Kategori
            $sheet->getColumnDimension('D')->setWidth(40); // Deskripsi
            $sheet->getColumnDimension('E')->setWidth(15); // Harga
            $sheet->getColumnDimension('F')->setWidth(15); // Stok
            $sheet->getColumnDimension('G')->setWidth(15); // Status
            $sheet->getColumnDimension('H')->setWidth(15); // Favorit

            // Add export info on the right side
            $sheet->setCellValue('J1', 'Diekspor pada: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue('J2', 'Total Produk: ' . ($row - 2));

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
            header('Content-Disposition: attachment;filename="export_produk_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');

            exit();
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Gagal mengekspor produk: ' . $e->getMessage());
        }
    }

    /**
     * Generate template for bulk updating products with consistent styling
     */
    public function exportForUpdate()
    {
        try {
            $products = Product::with('category')->get();
            // Ambil hanya kategori yang belum dihapus untuk dropdown
            $categories = Category::withoutTrashed()->pluck('name')->toArray();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Template Update Massal Produk')
                ->setSubject('Update Massal Produk')
                ->setDescription('Dibuat oleh Sistem Manajemen Seblak Sulthane');

            // Set headers - Hapus kolom "Favorit"
            $headers = [
                'ID',
                'Nama',
                'Kategori',
                'Deskripsi',
                'Harga',
                'Stok',
                'Status'
            ];

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

            // Ubah range styling dari A1:H1 menjadi A1:G1 (tanpa kolom Favorit)
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // Add data rows
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->id);
                $sheet->setCellValue('B' . $row, $product->name);
                $sheet->setCellValue('C' . $row, $product->category->name);
                $sheet->setCellValue('D' . $row, $product->description);
                $sheet->setCellValue('E' . $row, $product->price);
                $sheet->setCellValue('F' . $row, $product->stock);
                $sheet->setCellValue('G' . $row, $product->status ? 'AKTIF' : 'NONAKTIF');
                // Hapus pengisian cell untuk is_favorite

                // Protect the ID cell from editing
                $sheet->getStyle('A' . $row)->getProtection()
                    ->setLocked(true);

                // Highlight ID column to indicate it shouldn't be modified
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9D9D9'); // Light gray background

                // Add dropdown for Category column - hanya kategori aktif
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
                $activeValidation->setFormula1('"AKTIF,NONAKTIF"');

                // Alternate row colors for data rows
                if ($row % 2 == 0) {
                    $sheet->getStyle('B' . $row . ':G' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                } else {
                    $sheet->getStyle('B' . $row . ':G' . $row)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFFFF');
                }

                $row++;
            }

            // Format the entire data area
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:G' . ($row - 1))->applyFromArray($borderStyle);

            // Add instructions section
            $sheet->setCellValue('I1', 'PETUNJUK');
            $sheet->getStyle('I1')->applyFromArray([
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
                "1. JANGAN memodifikasi kolom ID (kolom A)",
                "2. Untuk Kategori, pilih dari dropdown yang tersedia",
                "3. Harga dan Stok harus berupa angka",
                "4. Untuk kolom Status, pilih BENAR atau SALAH dari dropdown",
                "5. Kolom yang tidak ingin diubah bisa dibiarkan apa adanya",
                "6. Sistem hanya akan mengupdate kolom yang Anda modifikasi",
                "7. Jangan mengubah baris header",
                "8. Setelah selesai, simpan dan unggah file untuk melakukan update"
            ];

            $instructionText = implode("\n\n", $instructions);
            $sheet->setCellValue('I2', $instructionText);
            $sheet->getStyle('I2:I9')->applyFromArray([
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
            $sheet->mergeCells('I2:I9');
            $sheet->getColumnDimension('I')->setWidth(50);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10);  // ID
            $sheet->getColumnDimension('B')->setWidth(30);  // Nama
            $sheet->getColumnDimension('C')->setWidth(20);  // Kategori
            $sheet->getColumnDimension('D')->setWidth(40);  // Deskripsi
            $sheet->getColumnDimension('E')->setWidth(15);  // Harga
            $sheet->getColumnDimension('F')->setWidth(15);  // Stok
            $sheet->getColumnDimension('G')->setWidth(15);  // Status

            // Set the auto-filter
            $sheet->setAutoFilter('A1:G' . ($row - 1));

            // Freeze panes (first row and ID column)
            $sheet->freezePane('B2');

            // Create the writer and output the file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="produk_untuk_update_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('products.index')
                ->with('error', 'Gagal mengekspor produk: ' . $e->getMessage());
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
                        $errors[] = "Baris " . ($index + 2) . ": Produk dengan ID {$row[0]} tidak ditemukan";
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
                    $errors[] = "Baris " . ($index + 2) . ": " . $e->getMessage();
                    \Log::error("Error processing row " . ($index + 2) . ": " . $e->getMessage());
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "Ringkasan pembaruan produk: {$updateCount} produk diperbarui";
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} produk tidak berubah";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= ". Namun, terdapat beberapa kesalahan: " . implode("; ", $errors);
                    return redirect()->route('products.index')->with('warning', $message);
                } else {
                    $message .= ". Namun, terdapat " . count($errors) . " kesalahan. Beberapa diantaranya: " .
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
                ->with('error', 'Kesalahan dalam memperbarui produk: ' . $e->getMessage());
        }
    }

    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            $totalProducts = Product::count();

            // Lakukan soft delete dengan mengupdate deleted_at
            $deletedCount = Product::query()->update(['deleted_at' => now()]);

            // Hapus gambar produk (opsional)
            $products = Product::withTrashed()->whereNotNull('image')->get();
            foreach ($products as $product) {
                if ($product->image) {
                    $imagePath = public_path($product->image);
                    if (file_exists($imagePath)) {
                        try {
                            unlink($imagePath);
                        } catch (\Exception $e) {
                            \Log::warning("Gagal menghapus gambar: " . $e->getMessage());
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', "Berhasil mengarsipkan {$deletedCount} dari {$totalProducts} produk.");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal mengarsipkan produk: ' . $e->getMessage());

            return redirect()->route('products.index')
                ->with('error', "Gagal mengarsipkan produk: " . $e->getMessage());
        }
    }
}
