<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                $imagePath = storage_path('app/public/products/' . basename($product->image));
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

    // import
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('file');

        try {
            DB::beginTransaction();

            // Correct way to call the static method
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $spreadsheet = $reader->load($file);
            $rows = $spreadsheet->getActiveSheet()->toArray();

            // Skip header row
            array_shift($rows);

            foreach ($rows as $row) {
                Product::create([
                    'name' => $row[0],
                    'category_id' => $row[1],
                    'description' => $row[2],
                    'price' => $row[3],
                    'stock' => $row[4],
                    'status' => $row[5] ?? 1,
                    'is_favorite' => $row[6] ?? 0,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', 'Products imported successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('products.index')
                ->with('error', 'Error importing products: ' . $e->getMessage());
        }
    }

    // generate template
    public function template()
    {
        $headers = [
            'Name',
            'Category ID',
            'Description',
            'Price',
            'Stock',
            'Status (1 = Active, 0 = Inactive)',
            'Is Favorite (1 = Yes, 0 = No)'
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(chr(65 + $index) . '1', $header);
        }

        // Add sample row
        $sampleData = [
            'Product Name',
            '1',
            'Product Description',
            '10000',
            '100',
            '1',
            '0'
        ];

        foreach ($sampleData as $index => $value) {
            $sheet->setCellValue(chr(65 + $index) . '2', $value);
        }

        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="products_template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }

    // export
    public function export()
    {
        try {
            $products = Product::with('category')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'Name',
                'Category ID',
                'Description',
                'Price',
                'Stock',
                'Status',
                'Is Favorite'
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Add data rows
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->name);
                $sheet->setCellValue('B' . $row, $product->category_id);
                $sheet->setCellValue('C' . $row, $product->description);
                $sheet->setCellValue('D' . $row, $product->price);
                $sheet->setCellValue('E' . $row, $product->stock);
                $sheet->setCellValue('F' . $row, $product->status);
                $sheet->setCellValue('G' . $row, $product->is_favorite);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="products.xlsx"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');

            exit();
        } catch (\Exception $e) {
            return redirect()
                ->route('products.index')
                ->with('error', 'Error exporting products: ' . $e->getMessage());
        }
    }

    // bulk update
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

            foreach ($rows as $index => $row) {
                // Skip empty rows
                if (empty($row[0])) continue;

                try {
                    $product = Product::where('id', $row[0])->first();

                    if (!$product) {
                        $errors[] = "Row " . ($index + 2) . ": Product with ID {$row[0]} not found";
                        continue;
                    }

                    $product->update([
                        'name' => $row[1] ?? $product->name,
                        'category_id' => $row[2] ?? $product->category_id,
                        'description' => $row[3] ?? $product->description,
                        'price' => $row[4] ?? $product->price,
                        'stock' => $row[5] ?? $product->stock,
                        'status' => $row[6] ?? $product->status,
                        'is_favorite' => $row[7] ?? $product->is_favorite,
                    ]);

                    $updateCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit();

            $message = "Successfully updated {$updateCount} products.";
            if (!empty($errors)) {
                $message .= " However, there were some errors: " . implode(", ", $errors);
                return redirect()->route('products.index')->with('warning', $message);
            }

            return redirect()->route('products.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('products.index')
                ->with('error', 'Error updating products: ' . $e->getMessage());
        }
    }

    public function exportForUpdate()
    {
        try {
            $products = Product::with('category')->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = [
                'ID',
                'Name',
                'Category ID',
                'Description',
                'Price',
                'Stock',
                'Status',
                'Is Favorite'
            ];

            foreach ($headers as $index => $header) {
                $sheet->setCellValue(chr(65 + $index) . '1', $header);
            }

            // Add data rows
            $row = 2;
            foreach ($products as $product) {
                $sheet->setCellValue('A' . $row, $product->id);
                $sheet->setCellValue('B' . $row, $product->name);
                $sheet->setCellValue('C' . $row, $product->category_id);
                $sheet->setCellValue('D' . $row, $product->description);
                $sheet->setCellValue('E' . $row, $product->price);
                $sheet->setCellValue('F' . $row, $product->stock);
                $sheet->setCellValue('G' . $row, $product->status);
                $sheet->setCellValue('H' . $row, $product->is_favorite);
                $row++;
            }

            $writer = new Xlsx($spreadsheet);

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

    // delete all products
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

            // Nonaktifkan foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Hapus semua produk
            Product::query()->delete();

            // Aktifkan kembali foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Commit transaksi
            DB::commit();
            \Log::info('Successfully deleted all products');

            return redirect()
                ->route('products.index')
                ->with('success', 'All products have been deleted successfully');
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
