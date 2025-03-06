<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
                ->with('error', 'Only owner can create new outlets');
        }

        return view('pages.outlets.create');
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can create new outlets');
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
        return redirect()->route('outlets.index')->with('success', 'Outlet created successfully');
    }

    public function edit(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can edit outlets');
        }

        return view('pages.outlets.edit', compact('outlet'));
    }

    public function update(Request $request, Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can update outlets');
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
        return redirect()->route('outlets.index')->with('success', 'Outlet updated successfully');
    }

    public function destroy(Outlet $outlet)
    {
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can delete outlets');
        }

        // Check for associated records
        if ($outlet->users()->exists() || $outlet->orders()->exists()) {
            return redirect()->route('outlets.index')
                ->with('error', 'Cannot delete outlet with associated users or orders');
        }

        $outlet->delete();
        return redirect()->route('outlets.index')->with('success', 'Outlet deleted successfully');
    }

    /**
     * Import outlets from Excel
     */
    public function import(Request $request)
    {
        // Ensure only owner can import outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can import outlets');
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

            foreach ($rows as $row) {
                // Skip empty rows
                if (empty($row[0])) continue;

                Outlet::create([
                    'name' => $row[0],
                    'address1' => $row[1],
                    'address2' => $row[2] ?: null,
                    'phone' => $row[3] ?: null,
                    'leader' => $row[4] ?: null,
                    'notes' => $row[5] ?: null,
                ]);
            }

            DB::commit();
            return redirect()->route('outlets.index')
                ->with('success', 'Outlets imported successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Error importing outlets: ' . $e->getMessage());
        }
    }

    /**
     * Generate template for outlets import
     */
    public function template()
    {
        // Ensure only owner can access template
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can access this feature');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set spreadsheet metadata
        $spreadsheet->getProperties()
            ->setCreator('Seblak Sulthane')
            ->setLastModifiedBy('Seblak Sulthane')
            ->setTitle('Template Import Outlet')
            ->setSubject('Template untuk import data outlet')
            ->setDescription('Download template ini, isi sesuai format, kemudian upload kembali.');

        // Set headers using Indonesian terms as requested
        $headers = ['NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

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

        // Format the header row
        $headerStyle = $sheet->getStyle('A1:F1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');

        // Auto-size columns
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="template_import_outlet.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Export outlets to Excel
     */
    public function export()
    {
        // Ensure only owner can export outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can export outlets');
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

            // Set headers using Indonesian terms as requested
            $headers = ['ID', 'NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET', 'TANGGAL DIBUAT'];
            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

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
                $row++;
            }

            // Format the header row
            $headerStyle = $sheet->getStyle('A1:H1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            // Auto-size columns
            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="data_outlet.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return redirect()->route('outlets.index')
                ->with('error', 'Error exporting outlets: ' . $e->getMessage());
        }
    }

    /**
     * Generate template for bulk updating outlets
     */
    public function exportForUpdate()
    {
        // Ensure only owner can access bulk update template
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can access this feature');
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

            // Set headers using Indonesian terms as requested
            $headers = ['ID', 'NAMA OUTLET', 'ALAMAT 1', 'ALAMAT 2', 'NO. TELP', 'PIMPINAN', 'KET'];
            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

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
                $row++;
            }

            // Format the header row
            $headerStyle = $sheet->getStyle('A1:G1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');

            // Add a note about ID column
            $sheet->setCellValue('A' . ($row + 1), 'Note: Jangan mengubah kolom ID karena akan digunakan sebagai referensi.');
            $sheet->mergeCells('A' . ($row + 1) . ':G' . ($row + 1));
            $sheet->getStyle('A' . ($row + 1))->getFont()->setBold(true);
            $sheet->getStyle('A' . ($row + 1))->getFont()->getColor()->setARGB('FF0000'); // Red color

            // Auto-size columns
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Create the writer and output the file
            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="form_update_outlet.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            return redirect()->route('outlets.index')
                ->with('error', 'Error preparing update template: ' . $e->getMessage());
        }
    }

    /**
     * Bulk update outlets from Excel file
     */
    public function bulkUpdate(Request $request)
    {
        // Ensure only owner can perform bulk updates
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can perform bulk updates');
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

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    $outlet = Outlet::findOrFail($row[0]);

                    $outlet->update([
                        'name' => $row[1] ?? $outlet->name,
                        'address1' => $row[2] ?? $outlet->address1,
                        'address2' => $row[3],
                        'phone' => $row[4],
                        'leader' => $row[5],
                        'notes' => $row[6],
                    ]);

                    $updateCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Successfully updated {$updateCount} outlets.";
            if (!empty($errors)) {
                $message .= " However, there were some errors: " . implode(", ", $errors);
                return redirect()->route('outlets.index')->with('warning', $message);
            }

            return redirect()->route('outlets.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Error updating outlets: ' . $e->getMessage());
        }
    }

    /**
     * Delete all outlets
     */
    public function deleteAll()
    {
        // Ensure only owner can delete all outlets
        if (Auth::user()->role !== 'owner') {
            return redirect()->route('outlets.index')
                ->with('error', 'Only owner can perform this action');
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
                    ->with('warning', "These outlets have associated users or orders and can't be deleted: {$outletNames}");
            }

            // Safe to delete all outlets
            Outlet::query()->delete();

            DB::commit();
            return redirect()->route('outlets.index')
                ->with('success', 'All outlets deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('outlets.index')
                ->with('error', 'Error deleting outlets: ' . $e->getMessage());
        }
    }
}
