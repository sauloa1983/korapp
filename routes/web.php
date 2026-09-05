<?php

use App\Http\Controllers\CustomerImportTemplateController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProductionOrderPdfController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleReceiptController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TutorialPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});

// Escaneo público de QR por parte del operario (desde el celular).
Route::get('/scan/{token}', [ScanController::class, 'show'])->name('scan.show');
Route::post('/scan/{token}', [ScanController::class, 'update'])->name('scan.update');

// Hoja de etiquetas QR imprimible (requiere sesión del panel).
Route::get('/production-orders/{order}/labels', [LabelController::class, 'show'])
    ->middleware(['auth', 'can:ViewAny,App\Models\ProductionOrder'])
    ->name('orders.labels');

Route::get('/production-orders/{order}/pdf', [ProductionOrderPdfController::class, 'show'])
    ->middleware(['auth', 'can:View,order'])
    ->name('orders.pdf');

// Documentos y exportables del panel (requieren sesión + permiso).
Route::middleware('auth')->group(function () {
    Route::get('/tutorial/imprimir', TutorialPrintController::class)
        ->name('tutorial.print');

    Route::get('/customers/import-template', CustomerImportTemplateController::class)
        ->name('customers.import-template');

    Route::get('/sales/{sale}/receipt', [SaleReceiptController::class, 'show'])
        ->middleware('can:View,sale')
        ->name('sales.receipt');

    Route::get('/quotes/{quote}/pdf', [QuotePdfController::class, 'show'])
        ->middleware('can:View,quote')
        ->name('quotes.pdf');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/reports/export/sales', [ReportController::class, 'sales'])->name('reports.export.sales');
        Route::get('/reports/export/rotation', [ReportController::class, 'rotation'])->name('reports.export.rotation');
    });
});
