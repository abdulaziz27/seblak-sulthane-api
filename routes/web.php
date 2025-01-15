<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OutletController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('pages.auth.login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('home', function () {
        return view('pages.dashboard');
    })->name('home');

    Route::delete('products/delete-all', [ProductController::class, 'deleteAll'])->name('products.deleteAll');
    Route::delete('categories/delete-all', [CategoryController::class, 'deleteAll'])->name('categories.deleteAll');

    // Resource routes
    Route::resource('users', UserController::class);
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('outlets', OutletController::class);
    Route::resource('members', MemberController::class);

    //post update products
    Route::post('products/update/{id}', [ProductController::class, 'update'])->name('products.newupdate');

    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::get('products/template', [ProductController::class, 'template'])->name('products.template');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::post('products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulkUpdate');
    Route::get('products/export-update', [ProductController::class, 'exportForUpdate'])->name('products.exportForUpdate');


    Route::post('categories/import', [CategoryController::class, 'import'])->name('categories.import');
    Route::get('categories/template', [CategoryController::class, 'template'])->name('categories.template');
    Route::get('categories/export', [CategoryController::class, 'export'])->name('categories.export');
    Route::post('categories/bulk-update', [CategoryController::class, 'bulkUpdate'])->name('categories.bulkUpdate');
    Route::get('categories/export-update', [CategoryController::class, 'exportForUpdate'])->name('categories.exportForUpdate');


    Route::get('members/{member}/history', [MemberController::class, 'orderHistory'])->name('members.history');
    Route::get('top-members', [MemberController::class, 'topMembers'])->name('members.top');
    // Route::get('/reports/outlet-sales', [ReportController::class, 'outletSalesReport'])->name('reports.outletSales');
    // Route::get('/reports/outlet-performance', [ReportController::class, 'outletPerformanceAnalysis'])->name('reports.outletPerformance');
});
