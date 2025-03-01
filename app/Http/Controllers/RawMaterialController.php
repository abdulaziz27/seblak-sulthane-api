<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

            foreach ($rows as $row) {
                if (empty($row[0])) continue;

                RawMaterial::create([
                    'name' => $row[0],
                    'unit' => $row[1],
                    'price' => $row[2],
                    'stock' => $row[3] ?? 0,
                    'description' => $row[4] ?? null,
                    'is_active' => $row[5] ?? true,
                ]);
            }

            DB::commit();
            return redirect()->route('raw-materials.index')
                ->with('success', 'Raw materials imported successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('raw-materials.index')
                ->with('error', 'Error importing raw materials: ' . $e->getMessage());
        }
    }

    /**
     * Export raw materials to Excel
     */
    public function export()
    {
        try {
            $materials = RawMaterial::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Add headers
            $headers = ['Name', 'Unit', 'Price', 'Stock', 'Description', 'Status'];
            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Add data rows
            $row = 2;
            foreach ($materials as $material) {
                $sheet->setCellValue('A' . $row, $material->name);
                $sheet->setCellValue('B' . $row, $material->unit);
                $sheet->setCellValue('C' . $row, $material->price);
                $sheet->setCellValue('D' . $row, $material->stock);
                $sheet->setCellValue('E' . $row, $material->description);
                $sheet->setCellValue('F' . $row, $material->is_active ? 'Active' : 'Inactive');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="raw_materials.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('raw-materials.index')
                ->with('error', 'Error exporting raw materials: ' . $e->getMessage());
        }
    }

    /**
     * Generate import template
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = ['Name', 'Unit', 'Price', 'Stock', 'Description', 'Is Active (1 = Yes, 0 = No)'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

        // Add sample row
        $sampleData = ['Sample Material', 'kg', '10000', '100', 'Sample description', '1'];
        foreach ($sampleData as $index => $value) {
            $sheet->setCellValue(chr(65 + $index) . '2', $value);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="raw_materials_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }
}
