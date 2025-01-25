<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;

Route::get('/', function () {
    return view('pages.auth.login');
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('home', [DashboardController::class, 'index'])->name('home');

    // Basic viewing routes for all users
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('outlets', [OutletController::class, 'index'])->name('outlets.index');
    Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');

    // Routes for admin and owner only
    Route::middleware('prevent-staff')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('members', MemberController::class);

        // Import/Export routes
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::post('products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulkUpdate');
        Route::post('categories/import', [CategoryController::class, 'import'])->name('categories.import');
        Route::post('categories/bulk-update', [CategoryController::class, 'bulkUpdate'])->name('categories.bulkUpdate');
    });

    // Owner only routes
    Route::middleware('owner-only')->group(function () {
        Route::resource('outlets', OutletController::class)->except(['index', 'show']);
        Route::delete('products/delete-all', [ProductController::class, 'deleteAll'])->name('products.deleteAll');
        Route::delete('categories/delete-all', [CategoryController::class, 'deleteAll'])->name('categories.deleteAll');
    });

    // Export routes (accessible by all)
    Route::get('products/template', [ProductController::class, 'template'])->name('products.template');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/export-update', [ProductController::class, 'exportForUpdate'])->name('products.exportForUpdate');
    Route::get('categories/template', [CategoryController::class, 'template'])->name('categories.template');
    Route::get('categories/export', [CategoryController::class, 'export'])->name('categories.export');
    Route::get('categories/export-update', [CategoryController::class, 'exportForUpdate'])->name('categories.exportForUpdate');
});
