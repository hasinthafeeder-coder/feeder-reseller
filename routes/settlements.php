<?php

use App\Http\Controllers\Settlement\SettlementInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reseller Settlement Invoices (read-only)
|--------------------------------------------------------------------------
|
| Resellers view their own persisted settlement invoices, deductions, and
| payment history. Ownership is enforced in the read service. Payments are
| recorded only by authorized Admin users.
|
*/
Route::prefix('settlements/invoices')
    ->middleware('permission:dashboard.view')
    ->group(function () {
        Route::get('/', [SettlementInvoiceController::class, 'index'])
            ->name('settlements.invoices.index');

        Route::get('/{invoice}', [SettlementInvoiceController::class, 'show'])
            ->name('settlements.invoices.show');

        Route::get('/{invoice}/print', [SettlementInvoiceController::class, 'print'])
            ->name('settlements.invoices.print');
    });
