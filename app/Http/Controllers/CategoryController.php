<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::paginate(10);
        return view('pages.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('pages.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Category::create([
            'name' => $request->name,
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
            'name' => 'required',
        ]);

        $category = Category::findOrFail($id);
        $category->update([
            'name' => $request->name,
        ]);

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Cek apakah kategori memiliki produk aktif
        if ($category->products()->exists()) {
            return redirect()->route('categories.index')->with('warning', 'Kategori ini masih memiliki produk aktif. Harap pindahkan atau hapus produk terlebih dahulu.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dihapus.');
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

            array_shift($rows);

            foreach ($rows as $row) {
                Category::create([
                    'name' => $row[0],
                ]);
            }

            DB::commit();
            return redirect()->route('categories.index')->with('success', 'Kategori berhasil diimpor.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('categories.index')->with('error', 'Error import kategori: ' . $e->getMessage());
        }
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Name');

        $sheet->setCellValue('A2', 'Sample Category');

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="categories_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }

    public function export()
    {
        try {
            $categories = Category::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'Name');

            $row = 2;
            foreach ($categories as $category) {
                $sheet->setCellValue('A' . $row, $category->name);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="categories.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('categories.index')->with('error', 'Error exporting categories: ' . $e->getMessage());
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

            array_shift($rows);

            $updateCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                if (empty($row[0])) continue;

                try {
                    $category = Category::where('id', $row[0])->first();

                    if (!$category) {
                        $errors[] = "Row " . ($index + 2) . ": Category with ID {$row[0]} not found";
                        continue;
                    }

                    $category->update([
                        'name' => $row[1] ?? $category->name,
                    ]);

                    $updateCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Successfully updated {$updateCount} categories.";
            if (!empty($errors)) {
                $message .= " However, there were some errors: " . implode(", ", $errors);
                return redirect()->route('categories.index')->with('warning', $message);
            }

            return redirect()->route('categories.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('categories.index')->with('error', 'Error updating categories: ' . $e->getMessage());
        }
    }

    public function exportForUpdate()
    {
        try {
            $categories = Category::all();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'ID');
            $sheet->setCellValue('B1', 'Name');

            $row = 2;
            foreach ($categories as $category) {
                $sheet->setCellValue('A' . $row, $category->id);
                $sheet->setCellValue('B' . $row, $category->name);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="categories_for_update.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit();
        } catch (\Exception $e) {
            return redirect()->route('categories.index')->with('error', 'Error exporting categories: ' . $e->getMessage());
        }
    }

    public function deleteAll()
    {
        try {
            DB::beginTransaction();

            // Cek apakah ada kategori yang masih memiliki produk
            $categoriesWithProducts = Category::has('products')->get();

            if ($categoriesWithProducts->isNotEmpty()) {
                $categoryNames = $categoriesWithProducts->pluck('name')->join(', ');
                return redirect()->route('categories.index')->with('warning', "Kategori berikut masih memiliki produk aktif: {$categoryNames}. Harap pindahkan atau hapus produk terlebih dahulu.");
            }

            // Jika tidak ada kategori yang berelasi dengan produk, lanjutkan penghapusan
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Category::query()->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            DB::commit();

            return redirect()->route('categories.index')->with('success', 'Semua kategori berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            return redirect()->route('categories.index')->with('error', 'Error deleting categories: ' . $e->getMessage());
        }
    }
}
