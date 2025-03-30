<?php

use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\MaterialOrderController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReportController;

Route::get('/', function () {
    return view('pages.auth.login');
});

// Password Reset Routes
Route::get('/forgot-password', [PasswordController::class, 'showForgotForm'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])
    ->middleware('guest')
    ->name('password.email');

Route::get('/reset-password/{token}', [PasswordController::class, 'showResetForm'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [PasswordController::class, 'resetPassword'])
    ->middleware('guest')
    ->name('password.update');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('home', [DashboardController::class, 'index'])->name('home');

    // Change Password Route
    Route::get('/change-password', [PasswordController::class, 'showChangeForm'])
        ->name('password.change');

    Route::put('/change-password', [PasswordController::class, 'changePassword'])
        ->name('password.update.change');


    // Basic viewing routes for all users (view only)
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('discounts', [DiscountController::class, 'index'])->name('discounts.index');
    Route::get('outlets', [OutletController::class, 'index'])->name('outlets.index');

    // Member only routes
    Route::resource('members', MemberController::class);


    // Owner only routes
    Route::middleware('owner-only')->group(function () {
        Route::delete('products/delete-all', [ProductController::class, 'deleteAll'])->name('products.deleteAll');
        Route::delete('categories/delete-all', [CategoryController::class, 'deleteAll'])->name('categories.deleteAll');
        Route::delete('outlets/delete-all', [OutletController::class, 'deleteAll'])->name('outlets.deleteAll');

        Route::resource('outlets', OutletController::class)->except(['index', 'show']);
        Route::post('outlets/import', [OutletController::class, 'import'])->name('outlets.import');
        Route::get('outlets/template', [OutletController::class, 'template'])->name('outlets.template');
        Route::get('outlets/export', [OutletController::class, 'export'])->name('outlets.export');
        Route::get('outlets/export-update', [OutletController::class, 'exportForUpdate'])->name('outlets.exportForUpdate');
        Route::post('outlets/bulk-update', [OutletController::class, 'bulkUpdate'])->name('outlets.bulkUpdate');
    });


    // Routes for admin and owner only
    Route::middleware('prevent-staff')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::resource('categories', CategoryController::class)->except(['index', 'show']);
        Route::resource('discounts', DiscountController::class)->except('index');
        Route::resource('outlets', OutletController::class)->except(['index', 'show']);

        // Import/Export routes
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::post('products/bulk-update', [ProductController::class, 'bulkUpdate'])->name('products.bulkUpdate');
        Route::post('categories/import', [CategoryController::class, 'import'])->name('categories.import');
        Route::post('categories/bulk-update', [CategoryController::class, 'bulkUpdate'])->name('categories.bulkUpdate');
    });

    // Export routes (accessible by all)
    Route::get('products/template', [ProductController::class, 'template'])->name('products.template');
    Route::get('products/export', [ProductController::class, 'export'])->name('products.export');
    Route::get('products/export-update', [ProductController::class, 'exportForUpdate'])->name('products.exportForUpdate');
    Route::get('categories/template', [CategoryController::class, 'template'])->name('categories.template');
    Route::get('categories/export', [CategoryController::class, 'export'])->name('categories.export');
    Route::get('categories/export-update', [CategoryController::class, 'exportForUpdate'])->name('categories.exportForUpdate');


    // Raw Materials routes
    Route::delete('raw-materials/delete-all', [RawMaterialController::class, 'deleteAll'])->name('raw-materials.deleteAll');
    Route::resource('raw-materials', RawMaterialController::class)->except(['show']);
    Route::post('raw-materials/update-stock/{rawMaterial}', [RawMaterialController::class, 'updateStock'])->name('raw-materials.update-stock');
    Route::post('raw-materials/import', [RawMaterialController::class, 'import'])->name('raw-materials.import');
    Route::get('raw-materials/export', [RawMaterialController::class, 'export'])->name('raw-materials.export');
    Route::get('raw-materials/template', [RawMaterialController::class, 'template'])->name('raw-materials.template');
    Route::get('raw-materials/export-for-update', [RawMaterialController::class, 'exportForUpdate'])->name('raw-materials.exportForUpdate');
    Route::post('raw-materials/bulk-update', [RawMaterialController::class, 'bulkUpdate'])->name('raw-materials.bulkUpdate');

    // Material Orders routes
    Route::resource('material-orders', MaterialOrderController::class)->except(['destroy']);
    Route::post('material-orders/{materialOrder}/update-status', [MaterialOrderController::class, 'updateStatus'])->name('material-orders.update-status');
    Route::delete('material-orders/{materialOrder}/cancel', [MaterialOrderController::class, 'cancel'])->name('material-orders.cancel');

    // Reports
    // Route::middleware('prevent-staff')->prefix('reports')->name('reports.')->group(function () {
    //     Route::get('reports/supplier-purchases', [ReportController::class, 'supplierPurchases'])->name('supplier-purchases');
    // });

    Route::get('/reports/sales-summary', [ReportController::class, 'salesSummary'])->name('reports.sales-summary');
    Route::get('/reports/material-purchases', [ReportController::class, 'materialPurchases'])->name('reports.material-purchases');

    // Raw Materials routes that require warehouse access
    Route::middleware('warehouse-access')->group(function () {
        Route::delete('raw-materials/delete-all', [RawMaterialController::class, 'deleteAll'])
            ->name('raw-materials.deleteAll');
        Route::get('raw-materials/template', [RawMaterialController::class, 'template'])->name('raw-materials.template');
        Route::post('raw-materials/import', [RawMaterialController::class, 'import'])
            ->name('raw-materials.import');
        Route::post('raw-materials/bulk-update', [RawMaterialController::class, 'bulkUpdate'])
            ->name('raw-materials.bulkUpdate');
        Route::get('raw-materials/export-for-update', [RawMaterialController::class, 'exportForUpdate'])
            ->name('raw-materials.exportForUpdate');
        Route::resource('raw-materials', RawMaterialController::class)->except(['index', 'show']);
        Route::post('raw-materials/update-stock/{rawMaterial}', [RawMaterialController::class, 'updateStock'])
            ->name('raw-materials.update-stock');

        // Reports related to warehouse
        Route::get('reports/supplier-purchases', [ReportController::class, 'supplierPurchases'])
            ->name('reports.supplier-purchases');
    });

    Route::get('raw-materials', [RawMaterialController::class, 'index'])->name('raw-materials.index');
    Route::get('raw-materials/export', [RawMaterialController::class, 'export'])->name('raw-materials.export');


    // Profile
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
});
