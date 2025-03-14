<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $query = Outlet::query();

        // Filter by outlet based on role
        if (Auth::user()->role !== 'owner') {
            $query->where('id', Auth::user()->outlet_id);
        }

        // Search functionality
        if ($request->name) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        $outlets = $query->latest()->paginate(10);

        // Pass user role to view for conditional rendering
        return view('pages.outlets.index', compact('outlets'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat membuat outlet baru');
        }

        return view('pages.outlets.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat membuat outlet baru');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'leader' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Outlet::create($request->all());
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil dibuat');
    }

    public function edit(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat mengedit outlet');
        }

        return view('pages.outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat memperbarui outlet');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'leader' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $outlet->update($request->all());
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil diperbarui');
    }

    public function destroy(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat menghapus outlet');
        }

        // Check for associated records
        if ($outlet->users()->exists() || $outlet->orders()->exists()) {
            return redirect()->route('outlets.index')
                ->with('error', 'Tidak dapat menghapus outlet yang memiliki pengguna atau pesanan terkait');
        }

        $outlet->delete();
        return redirect()->route('outlets.index')->with('success', 'Outlet berhasil dihapus');
    }

    /**
     * Generate template for outlets import with consistent styling
     */
    public function template()
    {
        // Ensure only owner can access template
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat mengakses fitur ini');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set spreadsheet metadata
        $spreadsheet->getProperties()
            ->setCreator('Seblak Sulthane')
            ->setLastModifiedBy('Seblak Sulthane')
            ->setTitle('Template Import Outlet')
            ->setSubject('Template untuk Import Outlet')
            ->setDescription('Unduh template ini, isi sesuai format, kemudian unggah kembali.');

        // Set headers using Indonesian terms as requested
        $headers = ['NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET'];

        // Add headers
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
            'Outlet Seblak ABC',
            'Jl. Merdeka No. 123',
            'Kel. Sukajadi, Kec. Sukabumi',
            '08123456789',
            'John Doe',
            'Cabang utama'
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

        // Apply styling to empty data rows - increased to 100 rows
        $sheet->getStyle('A3:F100')->applyFromArray($dataRowStyle);

        // Add alternating row colors
        for ($i = 3; $i <= 100; $i++) {
            if ($i % 2 == 1) { // Odd rows
                $sheet->getStyle('A' . $i . ':F' . $i)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(30); // NAMA OUTLET
        $sheet->getColumnDimension('B')->setWidth(40); // ALAMAT 1
        $sheet->getColumnDimension('C')->setWidth(40); // ALAMAT 2
        $sheet->getColumnDimension('D')->setWidth(20); // NO. TELP
        $sheet->getColumnDimension('E')->setWidth(25); // PIMPINAN
        $sheet->getColumnDimension('F')->setWidth(30); // KET

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
        $instructions = [
            "1. Isi data outlet mulai dari baris ke-2",
            "2. Jangan mengubah header pada baris pertama",
            "3. Kolom NAMA OUTLET dan ALAMAT 1 wajib diisi",
            "4. Kolom lainnya bersifat opsional",
            "5. Template ini mendukung hingga 100 outlet"
        ];

        $instructionText = implode("\n\n", $instructions);
        $sheet->setCellValue('H2', $instructionText);
        $sheet->getStyle('H2:H6')->applyFromArray([
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
        $sheet->mergeCells('H2:H6');
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

        $sheet->setCellValue('H8', 'KETERANGAN KOLOM');
        $sheet->getStyle('H8')->applyFromArray($fieldExplanationHeaderStyle);

        $fieldExplanations = [
            'NAMA OUTLET' => 'Nama outlet (wajib diisi)',
            'ALAMAT 1' => 'Alamat utama outlet (wajib diisi)',
            'ALAMAT 2' => 'Alamat tambahan seperti kelurahan, kecamatan (opsional)',
            'NO. TELP' => 'Nomor telepon outlet (opsional)',
            'PIMPINAN' => 'Nama pimpinan/penanggung jawab outlet (opsional)',
            'KET' => 'Catatan tambahan mengenai outlet (opsional)'
        ];

        $fieldRow = 9;
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

        $sheet->getStyle('H9:H14')->applyFromArray($fieldExplanationStyle);

        // Set the auto-filter for the data
        $sheet->setAutoFilter('A1:F100');

        // Freeze the header row
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_import_outlet.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Export outlets to Excel with consistent styling
     */
    public function export()
    {
        // Ensure only owner can export outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat mengekspor outlet');
        }

        try {
            $outlets = Outlet::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Data Outlet')
                ->setSubject('Daftar Outlet Seblak Sulthane')
                ->setDescription('Daftar lengkap outlet Seblak Sulthane');

            // Set headers
            $headers = ['ID', 'NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET', 'TANGGAL DIBUAT'];

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

            // Add data rows
            $row = 2;
            foreach ($outlets as $outlet) {
                $sheet->setCellValue('A' . $row, $outlet->id);
                $sheet->setCellValue('B' . $row, $outlet->name);
                $sheet->setCellValue('C' . $row, $outlet->address1);
                $sheet->setCellValue('D' . $row, $outlet->address2);
                $sheet->setCellValue('E' . $row, $outlet->phone);
                $sheet->setCellValue('F' . $row, $outlet->leader);
                $sheet->setCellValue('G' . $row, $outlet->notes);
                $sheet->setCellValue('H' . $row, $outlet->created_at->format('Y-m-d H:i:s'));

                // Alternate row colors
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F2F2');
                } else {
                    $sheet->getStyle('A' . $row . ':H' . $row)->getFill()
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

            // Add summary statistics
            $sheet->setCellValue('J1', 'INFORMASI EXPORT');
            $sheet->getStyle('J1')->getFont()->setBold(true);
            $sheet->getStyle('J1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4472C4');
            $sheet->getStyle('J1')->getFont()->getColor()->setARGB('FFFFFF');
            $sheet->getStyle('J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('J2', 'Tanggal Export: ' . now()->format('d M Y H:i:s'));
            $sheet->setCellValue('J3', 'Jumlah Outlet: ' . count($outlets));
            $sheet->setCellValue('J4', 'Export Oleh: ' . Auth::user()->name);

            // Format the summary section
            $summaryStyle = [
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
            $sheet->getStyle('J2:J4')->applyFromArray($summaryStyle);
            $sheet->getColumnDimension('J')->setWidth(35);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(10);  // ID
            $sheet->getColumnDimension('B')->setWidth(30);  // NAMA OUTLET
            $sheet->getColumnDimension('C')->setWidth(40);  // ALAMAT 1
            $sheet->getColumnDimension('D')->setWidth(40);  // ALAMAT 2
            $sheet->getColumnDimension('E')->setWidth(20);  // NO. TELP
            $sheet->getColumnDimension('F')->setWidth(25);  // PIMPINAN
            $sheet->getColumnDimension('G')->setWidth(30);  // KET
            $sheet->getColumnDimension('H')->setWidth(20);  // TANGGAL DIBUAT

            // Set the auto-filter
            $sheet->setAutoFilter('A1:H' . ($row - 1));

            // Freeze panes (first row and ID column)
            $sheet->freezePane('B2');

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="data_outlet_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return redirect()->route('outlets.index')
                ->with('error', 'Terjadi kesalahan saat mengekspor outlet: ' . $e->getMessage());
        }
    }

    /**
     * Generate template for bulk updating outlets with consistent styling
     */
    public function exportForUpdate()
    {
        // Ensure only owner can access bulk update template
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat mengakses fitur ini');
        }

        try {
            $outlets = Outlet::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set spreadsheet metadata
            $spreadsheet->getProperties()
                ->setCreator('Seblak Sulthane')
                ->setLastModifiedBy('Seblak Sulthane')
                ->setTitle('Data Outlet untuk Update')
                ->setSubject('Form Update Massal Outlet')
                ->setDescription('Update data outlet secara massal');

            // Set headers
            $headers = ['ID', 'NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET'];

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

            // Add data rows
            $row = 2;
            foreach ($outlets as $outlet) {
                $sheet->setCellValue('A' . $row, $outlet->id);
                $sheet->setCellValue('B' . $row, $outlet->name);
                $sheet->setCellValue('C' . $row, $outlet->address1);
                $sheet->setCellValue('D' . $row, $outlet->address2);
                $sheet->setCellValue('E' . $row, $outlet->phone);
                $sheet->setCellValue('F' . $row, $outlet->leader);
                $sheet->setCellValue('G' . $row, $outlet->notes);

                // Protect the ID cell from editing
                $sheet->getStyle('A' . $row)->getProtection()
                    ->setLocked(true);

                // Highlight ID column to indicate it shouldn't be modified
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9D9D9'); // Light gray background

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

            // Add instructions
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
                "2. Kolom ID digunakan sebagai referensi untuk mengidentifikasi outlet",
                "3. Update data outlet dengan mengubah nilai pada kolom yang ingin diperbarui",
                "4. Kolom NAMA OUTLET dan ALAMAT 1 wajib diisi",
                "5. Kolom lainnya bersifat opsional",
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
            $sheet->getColumnDimension('B')->setWidth(30);  // NAMA OUTLET
            $sheet->getColumnDimension('C')->setWidth(40);  // ALAMAT 1
            $sheet->getColumnDimension('D')->setWidth(40);  // ALAMAT 2
            $sheet->getColumnDimension('E')->setWidth(20);  // NO. TELP
            $sheet->getColumnDimension('F')->setWidth(25);  // PIMPINAN
            $sheet->getColumnDimension('G')->setWidth(30);  // KET

            // Set the auto-filter
            $sheet->setAutoFilter('A1:G' . ($row - 1));

            // Freeze panes (first row and ID column)
            $sheet->freezePane('B2');

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="form_update_outlet_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return redirect()->route('outlets.index')
                ->with('error', 'Terjadi kesalahan saat menyiapkan template update: ' . $e->getMessage());
        }
    }

    /**
     * Improved import outlets from Excel
     */
    public function import(Request $request)
    {
        // Ensure only owner can import outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat mengimpor outlet');
        }

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
                        $errors[] = "Baris {$rowNumber}: Nama outlet wajib diisi";
                        continue;
                    }

                    if (empty($row[1])) {
                        $errors[] = "Baris {$rowNumber}: Alamat 1 wajib diisi";
                        continue;
                    }

                    // Prepare data
                    $outletData = [
                        'name' => trim($row[0]),
                        'address1' => trim($row[1]),
                        'address2' => isset($row[2]) ? trim($row[2]) : null,
                        'phone' => isset($row[3]) ? trim($row[3]) : null,
                        'leader' => isset($row[4]) ? trim($row[4]) : null,
                        'notes' => isset($row[5]) ? trim($row[5]) : null,
                    ];

                    // Check if outlet already exists by name
                    $existingOutlet = Outlet::where('name', $outletData['name'])->first();

                    if ($existingOutlet) {
                        // Update existing outlet
                        $existingOutlet->update($outletData);
                        $updateCount++;
                    } else {
                        // Create new outlet
                        Outlet::create($outletData);
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
                $message .= "{$importCount} outlet baru berhasil ditambahkan. ";
            }

            if ($updateCount > 0) {
                $message .= "{$updateCount} outlet yang sudah ada berhasil diperbarui. ";
            }

            if ($importCount === 0 && $updateCount === 0) {
                $message = "Tidak ada outlet yang ditambahkan atau diperbarui. ";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= "Namun, terdapat beberapa error: " . implode("; ", $errors);
                    return redirect()->route('outlets.index')->with('warning', $message);
                } else {
                    $message .= "Namun, terdapat " . count($errors) . " error. Beberapa di antaranya: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('outlets.index')->with('warning', $message);
                }
            }

            return redirect()->route('outlets.index')
                ->with('success', trim($message));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Terjadi kesalahan saat mengimpor outlet: ' . $e->getMessage());
        }
    }

    /**
     * Improved bulk update outlets from Excel file
     */
    public function bulkUpdate(Request $request)
    {
        // Ensure only owner can perform bulk updates
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat melakukan update massal');
        }

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
                    // Find outlet by ID
                    $outlet = Outlet::find($row[0]);

                    if (!$outlet) {
                        $errors[] = "Baris {$rowNumber}: Outlet dengan ID {$row[0]} tidak ditemukan";
                        continue;
                    }

                    // Validate essential fields
                    if (empty($row[1])) {
                        $errors[] = "Baris {$rowNumber}: Nama outlet wajib diisi";
                        continue;
                    }

                    if (empty($row[2])) {
                        $errors[] = "Baris {$rowNumber}: Alamat 1 wajib diisi";
                        continue;
                    }

                    // Prepare data
                    $updates = [];
                    $hasChanges = false;

                    // Check each field for changes
                    if ($row[1] !== $outlet->name) {
                        $updates['name'] = trim($row[1]);
                        $hasChanges = true;
                    }

                    if ($row[2] !== $outlet->address1) {
                        $updates['address1'] = trim($row[2]);
                        $hasChanges = true;
                    }

                    // Optional fields - check and update only if different
                    if (isset($row[3]) && $row[3] !== $outlet->address2) {
                        $updates['address2'] = trim($row[3]);
                        $hasChanges = true;
                    }

                    if (isset($row[4]) && $row[4] !== $outlet->phone) {
                        $updates['phone'] = trim($row[4]);
                        $hasChanges = true;
                    }

                    if (isset($row[5]) && $row[5] !== $outlet->leader) {
                        $updates['leader'] = trim($row[5]);
                        $hasChanges = true;
                    }

                    if (isset($row[6]) && $row[6] !== $outlet->notes) {
                        $updates['notes'] = trim($row[6]);
                        $hasChanges = true;
                    }

                    // Only update if there are changes
                    if ($hasChanges) {
                        $outlet->update($updates);
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
            $message = "{$updateCount} outlet berhasil diperbarui.";

            if ($unchangedCount > 0) {
                $message .= " {$unchangedCount} outlet tidak ada perubahan.";
            }

            if (!empty($errors)) {
                if (count($errors) <= 3) {
                    $message .= " Namun, terdapat beberapa error: " . implode("; ", $errors);
                    return redirect()->route('outlets.index')->with('warning', $message);
                } else {
                    $message .= " Namun, terdapat " . count($errors) . " error. Beberapa di antaranya: " .
                        implode("; ", array_slice($errors, 0, 3)) . "...";
                    return redirect()->route('outlets.index')->with('warning', $message);
                }
            }

            return redirect()->route('outlets.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Terjadi kesalahan saat memperbarui outlet: ' . $e->getMessage());
        }
    }

    /**
     * Improved delete all outlets function
     */
    public function deleteAll()
    {
        // Ensure only owner can delete all outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Hanya pemilik yang dapat melakukan tindakan ini');
        }

        try {
            DB::beginTransaction();

            // Check for outlets with associated records
            $outletsWithRecords = Outlet::whereHas('users')
                ->orWhereHas('orders')
                ->get();

            if ($outletsWithRecords->isNotEmpty()) {
                $outletNames = $outletsWithRecords->pluck('name')->join(', ');
                return redirect()->route('outlets.index')
                    ->with('warning', "Outlet berikut tidak dapat dihapus karena memiliki data terkait (pengguna atau pesanan): {$outletNames}");
            }

            // Get count for reporting
            $outletCount = Outlet::count();

            // Safe to delete all outlets
            Outlet::query()->delete();

            DB::commit();

            if ($outletCount > 0) {
                return redirect()->route('outlets.index')
                    ->with('success', "Berhasil menghapus {$outletCount} outlet.");
            } else {
                return redirect()->route('outlets.index')
                    ->with('info', "Tidak ada outlet yang dihapus.");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Terjadi kesalahan saat menghapus outlet: ' . $e->getMessage());
        }
    }
}
