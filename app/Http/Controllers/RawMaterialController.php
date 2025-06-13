<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\MaterialOrderItem;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // Mendapatkan outlet_id dan is_warehouse dari user
        $user = Auth::user();
        $userOutletId = $user->outlet_id;
        $isWarehouse = $user->outlet->is_warehouse ?? false;
        
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

        $materials = $query->paginate(70);

        return view('pages.raw-materials.index', compact(
            'materials',
            'totalCount',
            'activeCount',
            'lowStockCount',
            'totalValue',
            'userOutletId',
            'isWarehouse'
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
            'purchase_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Buat bahan baku
            $rawMaterial = RawMaterial::create($request->all());

            // Jika ada stok awal, catat sebagai pembelian dari supplier
            if ($request->stock > 0) {
                StockAdjustment::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $request->stock,
                    'purchase_price' => $request->purchase_price,
                    'adjustment_date' => now(),
                    'adjustment_type' => 'purchase',
                    'notes' => 'Stok awal saat pembuatan bahan baku baru',
                    'user_id' => Auth::id()
                ]);
            }

            DB::commit();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Bahan baku berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal menambahkan bahan baku: ' . $e->getMessage());
        }
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
            'purchase_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Cek apakah ada perubahan stok
            $stockDifference = $request->stock - $rawMaterial->stock;

            // Update bahan baku
            $rawMaterial->update($request->all());

            // Jika stok bertambah, catat sebagai pembelian dari supplier
            if ($stockDifference > 0) {
                StockAdjustment::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $stockDifference,
                    'purchase_price' => $request->purchase_price,
                    'adjustment_date' => now(),
                    'adjustment_type' => 'purchase',
                    'notes' => 'Penambahan stok melalui form edit',
                    'user_id' => Auth::id()
                ]);
            }
            // Jika stok berkurang, catat sebagai penggunaan
            elseif ($stockDifference < 0) {
                StockAdjustment::create([
                    'raw_material_id' => $rawMaterial->id,
                    'quantity' => $stockDifference, // Nilai negatif
                    'purchase_price' => null,
                    'adjustment_date' => now(),
                    'adjustment_type' => 'usage',
                    'notes' => 'Pengurangan stok melalui form edit',
                    'user_id' => Auth::id()
                ]);
            }

            DB::commit();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Bahan baku berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal memperbarui bahan baku: ' . $e->getMessage());
        }
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
            'adjustment' => 'required|integer', // Hapus batasan lain pada adjustment
            'purchase_price' => 'nullable|numeric|min:0',
            'adjustment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $newStock = $rawMaterial->stock + $request->adjustment;

            // Hanya membatasi pengurangan stok, bukan penambahan
            if ($request->adjustment < 0) {
                // Pastikan tidak mengurangi stok yang direservasi
                $availableStock = $rawMaterial->stock - $rawMaterial->reserved_stock;

                if (abs($request->adjustment) > $availableStock) {
                    return redirect()->back()->with(
                        'error',
                        'Tidak dapat mengurangi stok yang telah direservasi. ' .
                            'Stok tersedia untuk pengurangan: ' . $availableStock . ' ' . $rawMaterial->unit
                    );
                }
            }

            // Update stok di raw_materials
            $updateData = ['stock' => $newStock];

            // Jika ini adalah pembelian baru (adjustment positif) dan harga beli diisi
            if ($request->adjustment > 0 && $request->filled('purchase_price')) {
                $updateData['purchase_price'] = $request->purchase_price;
            }

            $rawMaterial->update($updateData);

            // Catat riwayat penyesuaian stok
            $adjustmentType = $request->adjustment > 0 ? 'purchase' : 'usage';
            if ($request->has('adjustment_type')) {
                $adjustmentType = $request->adjustment_type;
            }

            // Simpan ke tabel stock_adjustments
            StockAdjustment::create([
                'raw_material_id' => $rawMaterial->id,
                'quantity' => abs($request->adjustment), // Pakai abs() untuk pastikan positif
                'purchase_price' => $request->adjustment > 0 ? $request->purchase_price : null,
                'adjustment_date' => $request->adjustment_date ?? now(),
                'adjustment_type' => $adjustmentType,
                'notes' => $request->notes,
                'user_id' => Auth::id()
            ]);

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
                        $errors[] = "Baris {$rowNumber}: Harga jual harus berupa angka";
                        continue;
                    }

                    if (!is_numeric($row[3])) {
                        $errors[] = "Baris {$rowNumber}: Harga beli harus berupa angka";
                        continue;
                    }

                    // Prepare data
                    $materialData = [
                        'name' => trim($row[0]),
                        'unit' => trim($row[1]),
                        'price' => (int)$row[2],   // Harga jual
                        'purchase_price' => (int)$row[3],  // Harga beli
                        'stock' => is_numeric($row[4]) ? (int)$row[4] : 0,
                        'description' => $row[5] ?? null,
                        'is_active' => 1  // Always set to active by default
                    ];

                    // Check if material already exists (by exact name match)
                    $existingMaterial = RawMaterial::where('name', $materialData['name'])->first();

                    if ($existingMaterial) {
                        // Calculate stock difference
                        $stockDifference = $materialData['stock'] - $existingMaterial->stock;

                        // Update existing material
                        $existingMaterial->update($materialData);

                        // Record stock adjustment if stock has changed
                        if ($stockDifference != 0) {
                            $type = $stockDifference > 0 ? 'purchase' : 'usage';
                            $notes = $stockDifference > 0
                                ? 'Penambahan stok melalui import Excel'
                                : 'Pengurangan stok melalui import Excel';

                            StockAdjustment::create([
                                'raw_material_id' => $existingMaterial->id,
                                'quantity' => abs($stockDifference),
                                'purchase_price' => $stockDifference > 0 ? $materialData['purchase_price'] : null,
                                'adjustment_date' => now(),
                                'adjustment_type' => $type,
                                'notes' => $notes,
                                'user_id' => Auth::id()
                            ]);
                        }

                        $updateCount++;
                    } else {
                        // Create new material
                        $newMaterial = RawMaterial::create($materialData);

                        // Record initial stock as purchase if stock > 0
                        if ($materialData['stock'] > 0) {
                            StockAdjustment::create([
                                'raw_material_id' => $newMaterial->id,
                                'quantity' => $materialData['stock'],
                                'purchase_price' => $materialData['purchase_price'],
                                'adjustment_date' => now(),
                                'adjustment_type' => 'purchase',
                                'notes' => 'Stok awal melalui import Excel',
                                'user_id' => Auth::id()
                            ]);
                        }

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

        // Add headers with Purchase Price column
        $headers = ['Nama', 'Satuan', 'Harga Jual', 'Harga Beli', 'Stok', 'Deskripsi'];
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
            '30000', // Harga jual
            '25000', // Harga beli
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

        $sheet->getStyle('A2:F2')->applyFromArray($sampleRowStyle);

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

        $sheet->getStyle('A3:F300')->applyFromArray($dataRowStyle);

        // Add alternating row colors for empty data rows
        for ($i = 3; $i <= 300; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':F' . $i)->getFill()
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
        $sheet->getColumnDimension('C')->setWidth(15); // Harga Jual
        $sheet->getColumnDimension('D')->setWidth(15); // Harga Beli
        $sheet->getColumnDimension('E')->setWidth(15); // Stok
        $sheet->getColumnDimension('F')->setWidth(40); // Deskripsi

        // Add instructions section
        $instructions = [
            "1. Isi detail bahan baku mulai dari baris 2",
            "2. Untuk kolom Satuan, gunakan dropdown untuk memilih jenis satuan",
            "3. Harga Jual adalah harga yang ditampilkan ke outlet",
            "4. Harga Beli adalah harga pembelian dari supplier (untuk laporan)",
            "5. Stok adalah jumlah stok awal dan akan dicatat sebagai pembelian dari supplier",
            "6. Semua kolom wajib diisi kecuali Deskripsi",
            "7. Template mendukung hingga 300 baris bahan baku",
            "8. Status Aktif akan otomatis diatur sebagai aktif"
        ];

        // Add instructions section
        $sheet->setCellValue('H1', 'PETUNJUK PENGISIAN');
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
        $sheet->getStyle('H2:H9')->applyFromArray([
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
        $sheet->mergeCells('H2:H9');
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

        $sheet->setCellValue('H11', 'PENJELASAN KOLOM');
        $sheet->getStyle('H11')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'Nama' => 'Nama bahan baku, contoh: "Bawang Merah"',
            'Satuan' => 'Satuan ukuran - pilih dari dropdown',
            'Harga Jual' => 'Harga jual ke outlet dalam Rupiah (angka saja)',
            'Harga Beli' => 'Harga beli dari supplier dalam Rupiah (angka saja)',
            'Stok' => 'Jumlah stok awal yang akan dicatat sebagai pembelian',
            'Deskripsi' => 'Informasi tambahan (opsional)'
        ];

        $fieldRow = 12;
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

        $sheet->getStyle('H12:H17')->applyFromArray($fieldExplanationStyle);

        // Set the auto-filter for the data
        $sheet->setAutoFilter('A1:F300');

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
     * Export bahan baku ke Excel dengan styling yang konsisten
     */
    public function export()
    {
        try {
            $materials = RawMaterial::all();
            // $isWarehouse = Auth::user()->role === 'owner' || Auth::user()->isWarehouseStaff();
            $isWarehouse = Auth::user()->role === 'owner' || (Auth::user()->role === 'admin' && Auth::user()->outlet && Auth::user()->outlet->is_warehouse);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set metadata spreadsheet
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Ekspor Bahan Baku')
                ->setSubject('Data Bahan Baku')
                ->setDescription('Dibuat oleh Sistem Manajemen Seblak Sulthane');

            // Tambahkan header - Disesuaikan berdasarkan peran pengguna
            if ($isWarehouse) {
                // Header lengkap untuk staf gudang
                $headers = ['ID', 'Nama', 'Satuan', 'Harga Jual', 'Harga Beli', 'Margin', 'Margin (%)', 'Stok', 'Nilai Total', 'Deskripsi', 'Status'];
                $lastCol = 'K';
            } else {
                // Header terbatas untuk pengguna biasa (tanpa harga beli dan margin)
                $headers = ['ID', 'Nama', 'Satuan', 'Harga Jual', 'Stok', 'Nilai Total', 'Deskripsi', 'Status'];
                $lastCol = 'H';
            }

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Styling header
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
            $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // Data baris
            $row = 2;
            $totalInventoryValue = 0;

            foreach ($materials as $material) {
                $itemValue = $material->stock * $material->price;
                $totalInventoryValue += $itemValue;

                if ($isWarehouse) {
                    // Data lengkap untuk staf gudang
                    $marginAmount = $material->price - $material->purchase_price;
                    $marginPercentage = $material->purchase_price > 0 ?
                        ($marginAmount / $material->purchase_price) * 100 : 0;

                    $sheet->setCellValue('A' . $row, $material->id);
                    $sheet->setCellValue('B' . $row, $material->name);
                    $sheet->setCellValue('C' . $row, $material->unit);
                    $sheet->setCellValue('D' . $row, $material->price);
                    $sheet->setCellValue('E' . $row, $material->purchase_price);
                    $sheet->setCellValue('F' . $row, $marginAmount);
                    $sheet->setCellValue('G' . $row, $marginPercentage);
                    $sheet->setCellValue('H' . $row, $material->stock);
                    $sheet->setCellValue('I' . $row, $itemValue);
                    $sheet->setCellValue('J' . $row, $material->description);
                    $sheet->setCellValue('K' . $row, $material->is_active ? 'Aktif' : 'Tidak Aktif');

                    // Format angka
                    $sheet->getStyle('D' . $row . ':F' . $row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00"%"');
                    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0');

                    // Sorot stok rendah
                    if ($material->stock < 10) {
                        $sheet->getStyle('H' . $row)
                            ->getFont()
                            ->getColor()
                            ->setARGB('FF0000');
                    }

                    // Warna baris selang-seling
                    if ($row % 2 == 0) {
                        $sheet->getStyle('A' . $row . ':K' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F2F2F2');
                    }
                } else {
                    // Data terbatas untuk pengguna biasa
                    $sheet->setCellValue('A' . $row, $material->id);
                    $sheet->setCellValue('B' . $row, $material->name);
                    $sheet->setCellValue('C' . $row, $material->unit);
                    $sheet->setCellValue('D' . $row, $material->price);
                    $sheet->setCellValue('E' . $row, $material->stock);
                    $sheet->setCellValue('F' . $row, $itemValue);
                    $sheet->setCellValue('G' . $row, $material->description);
                    $sheet->setCellValue('H' . $row, $material->is_active ? 'Aktif' : 'Tidak Aktif');

                    // Format angka
                    $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');

                    // Sorot stok rendah
                    if ($material->stock < 10) {
                        $sheet->getStyle('E' . $row)
                            ->getFont()
                            ->getColor()
                            ->setARGB('FF0000');
                    }

                    // Warna baris selang-seling
                    if ($row % 2 == 0) {
                        $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F2F2F2');
                    }
                }

                $row++;
            }

            // Tambah baris total
            $sheet->setCellValue('A' . $row, 'TOTAL');

            if ($isWarehouse) {
                $sheet->setCellValue('I' . $row, $totalInventoryValue);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('A' . $row . ':K' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DDEBF7');

                // Set border untuk semua data
                $sheet->getStyle('A1:K' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Set auto-filter
                $sheet->setAutoFilter('A1:K' . ($row - 1));
            } else {
                $sheet->setCellValue('F' . $row, $totalInventoryValue);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DDEBF7');

                // Set border untuk semua data
                $sheet->getStyle('A1:H' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Set auto-filter
                $sheet->setAutoFilter('A1:H' . ($row - 1));
            }

            $sheet->getStyle('A' . $row)->getFont()->setBold(true);

            // Tambahkan informasi ekspor di sisi kanan
            $infoCol = $isWarehouse ? 'M' : 'J';
            $sheet->setCellValue($infoCol . '1', 'Informasi Ekspor');
            $sheet->getStyle($infoCol . '1')->getFont()->setBold(true);
            $sheet->getStyle($infoCol . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle($infoCol . '1')->getFont()->getColor()->setARGB('FFFFFF');
            $sheet->getStyle($infoCol . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue($infoCol . '2', 'Dibuat pada: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue($infoCol . '3', 'Total Bahan Baku: ' . ($row - 2));
            $sheet->setCellValue($infoCol . '4', 'Total Nilai Inventaris: Rp ' . number_format($totalInventoryValue, 0, ',', '.'));
            $sheet->setCellValue($infoCol . '5', 'Bahan Stok Rendah: ' . $materials->where('stock', '<', 10)->count());

            $exportInfoStyle = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DDEBF7'] // Latar belakang biru muda
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle($infoCol . '2:' . $infoCol . '5')->applyFromArray($exportInfoStyle);
            $sheet->getStyle($infoCol . '5')->getFont()->getColor()->setARGB('FF0000'); // Warna merah untuk peringatan stok rendah
            $sheet->getColumnDimension($infoCol)->setWidth(40);

            // Set lebar kolom berdasarkan peran pengguna
            if ($isWarehouse) {
                $sheet->getColumnDimension('A')->setWidth(10);  // ID
                $sheet->getColumnDimension('B')->setWidth(30);  // Nama
                $sheet->getColumnDimension('C')->setWidth(15);  // Satuan
                $sheet->getColumnDimension('D')->setWidth(15);  // Harga Jual
                $sheet->getColumnDimension('E')->setWidth(15);  // Harga Beli
                $sheet->getColumnDimension('F')->setWidth(15);  // Margin
                $sheet->getColumnDimension('G')->setWidth(15);  // Margin (%)
                $sheet->getColumnDimension('H')->setWidth(15);  // Stok
                $sheet->getColumnDimension('I')->setWidth(20);  // Nilai Total
                $sheet->getColumnDimension('J')->setWidth(40);  // Deskripsi
                $sheet->getColumnDimension('K')->setWidth(15);  // Status
            } else {
                $sheet->getColumnDimension('A')->setWidth(10);  // ID
                $sheet->getColumnDimension('B')->setWidth(30);  // Nama
                $sheet->getColumnDimension('C')->setWidth(15);  // Satuan
                $sheet->getColumnDimension('D')->setWidth(15);  // Harga Jual
                $sheet->getColumnDimension('E')->setWidth(15);  // Stok
                $sheet->getColumnDimension('F')->setWidth(20);  // Nilai Total
                $sheet->getColumnDimension('G')->setWidth(40);  // Deskripsi
                $sheet->getColumnDimension('H')->setWidth(15);  // Status
            }

            // Bekukan baris header dan kolom ID
            $sheet->freezePane('B2');

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="ekspor_bahan_baku_' . date('Y-m-d') . '.xlsx"');
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

            // Add headers including purchase price column
            $headers = ['ID', 'Nama', 'Satuan', 'Harga Jual', 'Harga Beli', 'Stok', 'Deskripsi', 'Status Aktif'];
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
            foreach ($materials as $material) {
                $sheet->setCellValue('A' . $row, $material->id);
                $sheet->setCellValue('B' . $row, $material->name);
                $sheet->setCellValue('C' . $row, $material->unit);
                $sheet->setCellValue('D' . $row, $material->price);
                $sheet->setCellValue('E' . $row, $material->purchase_price);
                $sheet->setCellValue('F' . $row, $material->stock);
                $sheet->setCellValue('G' . $row, $material->description);
                $sheet->setCellValue('H' . $row, $material->is_active ? 'AKTIF' : 'NONAKTIF');

                // Protect the ID cell from editing
                $sheet->getStyle('A' . $row)->getProtection()
                    ->setLocked(true);

                // Highlight ID column to indicate it shouldn't be modified
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9D9D9'); // Light gray background

                // Create dropdown for Active column
                $validation = $sheet->getCell('H' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"AKTIF,NONAKTIF"');

                // Create dropdown for unit column
                $unitValidation = $sheet->getCell('C' . $row)->getDataValidation();
                $unitValidation->setType(DataValidation::TYPE_LIST);
                $unitValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $unitValidation->setAllowBlank(false);
                $unitValidation->setShowDropDown(true);
                $unitValidation->setFormula1('"Kg,Ball,Bks,Ikat,Pcs,Dus,Pack,Renteng,Botol,Slop,Box,Peti"');

                // Alternate row colors for data rows
                if ($row % 2 == 0) {
                    $sheet->getStyle('B' . $row . ':H' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                } else {
                    $sheet->getStyle('B' . $row . ':H' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFFFFF');
                }

                $row++;
            }

            // Format the entire data area
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->getStyle('A1:H' . ($row - 1))->applyFromArray($borderStyle);

            // Add instructions section
            $sheet->setCellValue('J1', 'PETUNJUK PENGISIAN');
            $sheet->getStyle('J1')->applyFromArray([
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
                "6. Penambahan stok akan dicatat sebagai pembelian dari supplier",
                "7. Pengurangan stok akan dicatat sebagai penggunaan stok",
                "8. Setelah selesai mengupdate, simpan file dan upload kembali"
            ];

            $instructionText = implode("\n\n", $instructions);
            $sheet->setCellValue('J2', $instructionText);
            $sheet->getStyle('J2:J9')->applyFromArray([
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
            $sheet->mergeCells('J2:J9');
            $sheet->getColumnDimension('J')->setWidth(50);

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

            $sheet->setCellValue('J12', 'PERHATIAN');
            $sheet->getStyle('J12')->applyFromArray($warningStyle);

            $sheet->setCellValue('J13', 'Jangan mengubah kolom ID karena akan digunakan sebagai referensi.');
            $sheet->setCellValue('J14', 'Perubahan pada nilai stok akan dicatat sebagai penyesuaian stok.');
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
            $sheet->getStyle('J13:J14')->applyFromArray($warningTextStyle);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10);  // ID
            $sheet->getColumnDimension('B')->setWidth(30);  // Nama
            $sheet->getColumnDimension('C')->setWidth(15);  // Satuan
            $sheet->getColumnDimension('D')->setWidth(15);  // Harga Jual
            $sheet->getColumnDimension('E')->setWidth(15);  // Harga Beli
            $sheet->getColumnDimension('F')->setWidth(15);  // Stok
            $sheet->getColumnDimension('G')->setWidth(40);  // Deskripsi
            $sheet->getColumnDimension('H')->setWidth(15);  // Status Aktif

            // Set the auto-filter
            $sheet->setAutoFilter('A1:H' . ($row - 1));

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
     * Bulk update raw materials from Excel
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

                    // Check and update purchase price if changed and valid
                    if (isset($row[4]) && is_numeric($row[4]) && (float)$row[4] !== (float)$material->purchase_price) {
                        $updates['purchase_price'] = (float)$row[4];
                        $hasChanges = true;
                    }

                    // Calculate stock difference if stock value is different
                    $oldStock = $material->stock;
                    $newStock = isset($row[5]) && is_numeric($row[5]) ? (int)$row[5] : $oldStock;
                    $stockDifference = $newStock - $oldStock;

                    if ($stockDifference != 0) {
                        $updates['stock'] = $newStock;
                        $hasChanges = true;
                    }

                    // Check and update description if changed
                    if (isset($row[6]) && $row[6] !== $material->description) {
                        $updates['description'] = $row[6];
                        $hasChanges = true;
                    }

                    // Check and update active status if changed
                    if (isset($row[7])) {
                        $newStatus = strtoupper($row[7]) === 'TRUE' ? 1 : 0;
                        if ($newStatus !== $material->is_active) {
                            $updates['is_active'] = $newStatus;
                            $hasChanges = true;
                        }
                    }

                    // Only update if there are changes
                    if ($hasChanges) {
                        $material->update($updates);

                        // Record stock adjustment if stock has changed
                        if ($stockDifference != 0) {
                            $type = $stockDifference > 0 ? 'purchase' : 'usage';
                            $notes = $stockDifference > 0
                                ? 'Penambahan stok melalui bulk update Excel'
                                : 'Pengurangan stok melalui bulk update Excel';

                            StockAdjustment::create([
                                'raw_material_id' => $material->id,
                                'quantity' => abs($stockDifference),
                                'purchase_price' => $stockDifference > 0 ? $material->purchase_price : null,
                                'adjustment_date' => now(),
                                'adjustment_type' => $type,
                                'notes' => $notes,
                                'user_id' => Auth::id()
                            ]);
                        }

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
     * Soft delete all raw materials
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

            // Soft delete all materials but don't delete stock adjustments
            // This will keep the purchase history intact
            RawMaterial::query()->delete();

            DB::commit();

            return redirect()->route('raw-materials.index')
                ->with('success', "Semua {$materialCount} bahan baku berhasil dihapus.");
        } catch (\Exception $e) {
            // Rollback transaction if still active
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return redirect()->route('raw-materials.index')
                ->with('error', 'Kesalahan menghapus bahan baku: ' . $e->getMessage());
        }
    }
}
