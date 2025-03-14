<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        // Add search functionality
        $query = Category::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $categories = $query->paginate(10);
        return view('pages.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('pages.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:categories,name|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description ?? null,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        return view('pages.categories.edit', compact('category'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        $category = Category::findOrFail($id);
        $category->update([
            'name' => $request->name,
            'description' => $request->description ?? $category->description,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Check if the category has associated products
        if ($category->products()->exists()) {
            return redirect()->route('categories.index')
                ->with('warning', 'Kategori ini memiliki produk yang masih aktif. Harap atur ulang atau hapus produk tersebut terlebih dahulu.');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
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
            ->setSubject('Kategori Seblak Sulthane')
            ->setDescription('Dihasilkan oleh Sistem Manajemen Seblak Sulthane');

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
    private function addInstructionsSection($sheet, $instructions, $startRow, $column = 'D')
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
            $duplicates = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because row 1 is the header and arrays are 0-indexed

                // Skip empty rows
                if (empty($row[0])) {
                    continue;
                }

                try {
                    // Sanitize name value
                    $name = trim($row[0]);
                    $description = isset($row[1]) ? trim($row[1]) : null;

                    // Check if name is too long (database constraint)
                    if (strlen($name) > 255) {
                        $errors[] = "Baris {$rowNumber}: Nama kategori melebihi panjang maksimum (255 karakter)";
                        continue;
                    }

                    // Check for duplicates in database
                    $existingCategory = Category::where('name', $name)->first();
                    if ($existingCategory) {
                        $duplicates[] = "Baris {$rowNumber}: Kategori '{$name}' sudah ada (ID: {$existingCategory->id})";
                        continue;
                    }

                    // Check for duplicates in current import batch
                    $isDuplicate = false;
                    foreach ($rows as $checkIndex => $checkRow) {
                        if ($checkIndex !== $index && $checkIndex < $index && !empty($checkRow[0]) && trim($checkRow[0]) === $name) {
                            $isDuplicate = true;
                            $duplicates[] = "Baris {$rowNumber}: Duplikat kategori '{$name}' ditemukan di baris " . ($checkIndex + 2);
                            break;
                        }
                    }

                    if ($isDuplicate) {
                        continue;
                    }

                    // Create category
                    Category::create([
                        'name' => $name,
                        'description' => $description,
                    ]);

                    $importCount++;
                } catch (\Exception $e) {
                    $errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            // Build a comprehensive message
            $message = "Berhasil mengimpor {$importCount} kategori.";

            if (!empty($duplicates)) {
                $dupMessage = count($duplicates) <= 3
                    ? implode("; ", $duplicates)
                    : implode("; ", array_slice($duplicates, 0, 3)) . "... dan " . (count($duplicates) - 3) . " lainnya";

                $message .= " {$dupMessage}";
                return redirect()->route('categories.index')->with('warning', $message);
            }

            if (!empty($errors)) {
                $errMessage = count($errors) <= 3
                    ? implode("; ", $errors)
                    : implode("; ", array_slice($errors, 0, 3)) . "... dan " . (count($errors) - 3) . " lainnya";

                $message .= " Terdapat kesalahan: {$errMessage}";
                return redirect()->route('categories.index')->with('warning', $message);
            }

            return redirect()->route('categories.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('categories.index')
                ->with('error', 'Terjadi kesalahan saat mengimpor kategori: ' . $e->getMessage());
        }
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Apply common styling
        $headerStyle = $this->applyCommonStyles($spreadsheet, 'Template Impor Kategori');

        // Headers
        $headers = ['Nama', 'Deskripsi (Opsional)'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

        // Apply header styling
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Add sample data
        $sampleData = [
            'Makanan Utama',
            'Kategori untuk menu makanan utama'
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
        $sheet->getStyle('A2:B2')->applyFromArray($sampleRowStyle);

        // Add more sample rows for different types of categories
        $moreSamples = [
            ['Minuman', 'Kategori untuk berbagai jenis minuman'],
            ['Camilan', 'Kategori untuk makanan ringan dan camilan'],
        ];

        $row = 3;
        foreach ($moreSamples as $sample) {
            $sheet->setCellValue('A' . $row, $sample[0]);
            $sheet->setCellValue('B' . $row, $sample[1]);

            // Alternate row colors
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            } else {
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2EFDA');
            }

            // Add borders
            $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

            $row++;
        }

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

        // Apply styling to empty data rows - increased to 200 rows
        $sheet->getStyle('A' . $row . ':B200')->applyFromArray($dataRowStyle);

        // Add alternating row colors for the rest of the template
        for ($i = $row; $i <= 200; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':B' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // Name
        $sheet->getColumnDimension('B')->setWidth(50); // Description

        // Add instructions section
        $instructions = [
            "1. Isi data kategori mulai dari baris ke-5",
            "2. Kolom 'Nama' wajib diisi dan harus unik",
            "3. Kolom 'Deskripsi' bersifat opsional",
            "4. Jangan mengubah judul kolom di baris 1",
            "5. Baris 2-4 berisi contoh - Anda dapat menghapus atau membiarkannya",
            "6. Template ini mendukung hingga 200 entri kategori",
            "7. Kategori dengan nama yang sama akan dilewati saat impor"
        ];
        $this->addInstructionsSection($sheet, $instructions, 1, 'D');

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

        $sheet->setCellValue('D10', 'PENJELASAN KOLOM');
        $sheet->getStyle('D10')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Nama' => 'Nama kategori (wajib), contoh: "Makanan Utama"',
            'Deskripsi' => 'Deskripsi opsional untuk kategori'
        ];

        $fieldRow = 11;
        foreach ($fieldExplanations as $field => $explanation) {
            $sheet->setCellValue('D' . $fieldRow, "$field: $explanation");
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

        $sheet->getStyle('D11:D12')->applyFromArray($fieldExplanationStyle);

        // Freeze the header row
        $sheet->freezePane('A2');

        // Set the auto-filter
        $sheet->setAutoFilter('A1:B200');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_kategori.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }

    public function export()
    {
        try {
            $categories = Category::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Apply common styling
            $headerStyle = $this->applyCommonStyles($spreadsheet, 'Ekspor Kategori');

            // Set headers
            $headers = [
                'ID',
                'Nama',
                'Deskripsi',
                'Jumlah Produk',
                'Dibuat Pada',
                'Diperbarui Pada'
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Apply header styling
            $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Add data rows
            $row = 2;
            foreach ($categories as $category) {
                $sheet->setCellValue('A' . $row, $category->id);
                $sheet->setCellValue('B' . $row, $category->name);
                $sheet->setCellValue('C' . $row, $category->description ?? '');
                $sheet->setCellValue('D' . $row, $category->products()->count());
                $sheet->setCellValue('E' . $row, $category->created_at->format('Y-m-d H:i:s'));
                $sheet->setCellValue('F' . $row, $category->updated_at->format('Y-m-d H:i:s'));

                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
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
            $sheet->getStyle('A1:F' . ($row - 1))->applyFromArray($borderStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10); // ID
            $sheet->getColumnDimension('B')->setWidth(30); // Name
            $sheet->getColumnDimension('C')->setWidth(50); // Description
            $sheet->getColumnDimension('D')->setWidth(15); // Products Count
            $sheet->getColumnDimension('E')->setWidth(20); // Created At
            $sheet->getColumnDimension('F')->setWidth(20); // Updated At

            // Add export info on the right side
            $sheet->setCellValue('H1', 'Diekspor pada: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue('H2', 'Total Kategori: ' . ($row - 2));

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
            $sheet->getStyle('H1:H2')->applyFromArray($exportInfoStyle);

            // Set column width for export info
            $sheet->getColumnDimension('H')->setWidth(35);

            // Freeze the header row
            $sheet->freezePane('A2');

            // Set the auto-filter
            $sheet->setAutoFilter('A1:F' . ($row - 1));

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="kategori_ekspor_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('categories.index')
                ->with('error', 'Terjadi kesalahan saat mengekspor kategori: ' . $e->getMessage());
        }
    }

    public function exportForUpdate()
    {
        try {
            $categories = Category::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Apply common styling
            $headerStyle = $this->applyCommonStyles($spreadsheet, 'Template Update Massal Kategori');

            // Set headers
            $headers = [
                'ID',
                'Nama',
                'Deskripsi'
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Apply header styling
            $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Add data rows
            $row = 2;
            foreach ($categories as $category) {
                $sheet->setCellValue('A' . $row, $category->id);
                $sheet->setCellValue('B' . $row, $category->name);
                $sheet->setCellValue('C' . $row, $category->description ?? '');

                // Protect ID column from editing
                $sheet->getStyle('A' . $row)->getProtection()
                    ->setLocked(true);

                // Highlight ID column to indicate it should not be changed
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DDDDDD');

                // Alternate row colors for data rows
                if ($row % 2 == 0) {
                    $sheet->getStyle('B' . $row . ':C' . $row)->getFill()
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
            $sheet->getStyle('A1:C' . ($row - 1))->applyFromArray($borderStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10); // ID
            $sheet->getColumnDimension('B')->setWidth(30); // Name
            $sheet->getColumnDimension('C')->setWidth(50); // Description

            // Add instructions section
            $instructions = [
                "1. JANGAN mengubah kolom ID (kolom A)",
                "2. Kolom 'Nama' harus unik untuk semua kategori",
                "3. Anda dapat mengubah kolom 'Nama' dan 'Deskripsi'",
                "4. Biarkan sel tidak berubah untuk nilai yang tidak ingin diperbarui",
                "5. Setiap baris mewakili kategori yang sudah ada",
                "6. Setelah selesai, simpan dan unggah file untuk melakukan pembaruan"
            ];
            $this->addInstructionsSection($sheet, $instructions, 1, 'E');

            // Warning for ID column
            $warningStyle = [
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

            $sheet->setCellValue('E12', 'PERINGATAN');
            $sheet->getStyle('E12')->applyFromArray($warningStyle);

            $sheet->setCellValue('E13', 'Jangan mengubah nilai ID di kolom A. Nilai ini digunakan untuk mengidentifikasi kategori mana yang akan diperbarui.');
            $warningTextStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FCE4D6'] // Light orange background
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'alignment' => [
                    'wrapText' => true,
                ],
            ];
            $sheet->getStyle('E13')->applyFromArray($warningTextStyle);

            // Freeze the ID column and header row
            $sheet->freezePane('B2');

            // Set the auto-filter
            $sheet->setAutoFilter('A1:C' . ($row - 1));

            // Protect the worksheet to prevent ID column editing
            $sheet->getProtection()->setSheet(true);

            // Allow editing of data cells
            for ($r = 2; $r < $row; $r++) {
                $sheet->getStyle('B' . $r . ':C' . $r)->getProtection()
                    ->setLocked(false);
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="kategori_untuk_update.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('categories.index')
                ->with('error', 'Terjadi kesalahan saat mengekspor kategori untuk pembaruan: ' . $e->getMessage());
        }
    }

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
            $duplicateNames = [];

            // First pass: check for duplicate names in the import file
            $nameMap = [];
            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1])) continue;

                $id = $row[0];
                $name = trim($row[1]);

                if (isset($nameMap[$name])) {
                    $duplicateNames[] = "Duplikat nama '{$name}' ditemukan di baris " . ($nameMap[$name] + 2) . " dan " . ($index + 2);
                } else {
                    $nameMap[$name] = $index;
                }
            }

            // Return early if duplicate names are found
            if (!empty($duplicateNames)) {
                DB::rollBack();
                return redirect()->route('categories.index')
                    ->with('error', 'Impor gagal: Ditemukan nama kategori duplikat dalam file unggahan: ' . implode('; ', $duplicateNames));
            }

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    $category = Category::find($row[0]);

                    if (!$category) {
                        $errors[] = "Baris " . ($index + 2) . ": Kategori dengan ID {$row[0]} tidak ditemukan";
                        continue;
                    }

                    // Check if the name already exists for another category
                    if (!empty($row[1]) && $row[1] !== $category->name) {
                        $existingCategory = Category::where('name', $row[1])
                            ->where('id', '!=', $category->id)
                            ->first();

                        if ($existingCategory) {
                            $errors[] = "Baris " . ($index + 2) . ": Tidak dapat memperbarui ke nama '{$row[1]}' karena sudah digunakan oleh kategori ID {$existingCategory->id}";
                            continue;
                        }
                    }

                    // Update only if fields have been modified
                    $updates = [];
                    $hasChanges = false;

                    // Check and update name if changed
                    if (!empty($row[1]) && $row[1] !== $category->name) {
                        $updates['name'] = $row[1];
                        $hasChanges = true;
                    }

                    // Check and update description if changed
                    if (isset($row[2]) && $row[2] !== $category->description) {
                        $updates['description'] = $row[2];
                        $hasChanges = true;
                    }

                    // Only update if there are changes
                    if ($hasChanges) {
                        $category->update($updates);
                        $updateCount++;
                        \Log::info("Updated category ID {$category->id}: " . json_encode($updates));
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
            $message = "Ringkasan pembaruan kategori: {$updateCount} kategori diperbarui";
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} kategori tidak berubah";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= ". Namun, terdapat beberapa kesalahan: " . implode("; ", $errors);
                    return redirect()->route('categories.index')->with('warning', $message);
                } else {
                    $message .= ". Namun, terdapat " . count($errors) . " kesalahan. Beberapa di antaranya: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('categories.index')->with('warning', $message);
                }
            }

            return redirect()->route('categories.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('categories.index')
                ->with('error', 'Terjadi kesalahan saat memperbarui kategori: ' . $e->getMessage());
        }
    }

    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            // Log start of operation
            \Log::info('Memulai proses penghapusan semua kategori');

            // Check if any categories have associated products
            $categoriesWithProducts = Category::whereHas('products')->get();

            if ($categoriesWithProducts->isNotEmpty()) {
                // Prepare detailed information about categories with products
                $categoryInfo = $categoriesWithProducts->map(function ($category) {
                    $productCount = $category->products()->count();
                    return "{$category->name} (ID: {$category->id}, Produk: {$productCount})";
                })->join(', ');

                // Roll back and return message
                DB::rollBack();
                return redirect()->route('categories.index')
                    ->with('warning', "Tidak dapat menghapus semua kategori. Kategori berikut masih memiliki produk terkait: {$categoryInfo}. Harap atur ulang atau hapus produk tersebut terlebih dahulu.");
            }

            // Track how many categories will be deleted
            $categoryCount = Category::count();

            if ($categoryCount === 0) {
                DB::rollBack();
                return redirect()->route('categories.index')
                    ->with('info', 'Tidak ada kategori yang ditemukan untuk dihapus.');
            }

            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Delete all categories
            Category::query()->delete();

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Commit the transaction
            DB::commit();

            // Log successful deletion
            \Log::info("Berhasil menghapus semua {$categoryCount} kategori");

            return redirect()->route('categories.index')
                ->with('success', "Semua {$categoryCount} kategori telah berhasil dihapus.");
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error dalam penghapusan kategori: ' . $e->getMessage());

            // Rollback transaction if still active
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Make sure foreign key checks are re-enabled
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return redirect()->route('categories.index')
                ->with('error', 'Terjadi kesalahan saat menghapus kategori: ' . $e->getMessage());
        }
    }
}
