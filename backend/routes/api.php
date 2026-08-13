<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/avatar/{file}', [BackupController::class, 'avatar']);

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/all', [ProductController::class, 'all']);
        Route::get('/export-excel', [ProductController::class, 'exportExcel'])->middleware('permission:inventory');
        Route::post('/import-excel', [ProductController::class, 'importExcel'])->middleware('permission:inventory');
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:inventory');
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:inventory');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:inventory');

        Route::post('/{product}/batches', [ProductController::class, 'addBatch'])->middleware('permission:inventory');
        Route::put('/batches/{batch}', [ProductController::class, 'updateBatch'])->middleware('permission:inventory');
        Route::delete('/batches/{batch}', [ProductController::class, 'destroyBatch'])->middleware('permission:inventory');
    });

    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales');
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/lookup/{invoice}', [SaleController::class, 'lookup'])->middleware('permission:sales');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales');
    Route::post('/sales/{sale}/refund', [SaleController::class, 'refund'])->middleware('permission:sales');
    Route::post('/print-receipt', [PrintController::class, 'receipt']);

    Route::prefix('purchases')->middleware('permission:purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::get('/{purchase}', [PurchaseController::class, 'show']);
        Route::delete('/{purchase}', [PurchaseController::class, 'destroy']);
    });

    Route::prefix('users')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers');
    Route::get('/customers/{customer}/payments', [CustomerController::class, 'payments']);
    Route::post('/customers/{customer}/payments', [CustomerController::class, 'receivePayment'])->middleware(EnsureAdmin::class);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::put('/companies/{company}', [CompanyController::class, 'update']);
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy']);

    Route::prefix('backup')->middleware(EnsureAdmin::class)->group(function () {
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/', [BackupController::class, 'run']);
        Route::post('/export-excel', [BackupController::class, 'exportExcel']);
        Route::post('/restore', [BackupController::class, 'restore']);
        Route::post('/delete', [BackupController::class, 'delete']);
        Route::get('/{path}', [BackupController::class, 'download'])->where('path', '.*');
    });

    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
    Route::get('/reports/sales-by-date', [ReportController::class, 'salesByDate']);
    Route::get('/reports/exports', [ReportController::class, 'export'])->middleware(EnsureAdmin::class);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');