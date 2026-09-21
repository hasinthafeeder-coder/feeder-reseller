<?php

use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\OrderImportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reseller Portal Orders
| Phase 1A — list, manual create
| Phase 1B — single-order operational workflow
| Phase 1C — courier selection & shipment booking
| Phase 1D — Order Pool / CCA assignment states
| Phase 1.2A — spreadsheet import (upload, validate, create)
| Phase 1.2B — import / owner CCA assignment (individual, random, pool)
|--------------------------------------------------------------------------
*/

Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index'])
        ->middleware('permission:orders.view')
        ->name('orders.index');

    Route::get('/create', [OrderController::class, 'create'])
        ->middleware('permission:orders.create')
        ->name('orders.create');

    Route::post('/', [OrderController::class, 'store'])
        ->middleware('permission:orders.create')
        ->name('orders.store');

    Route::get('/import/template', [OrderImportController::class, 'template'])
        ->middleware('permission:orders.create')
        ->name('orders.import.template');

    Route::post('/import/upload', [OrderImportController::class, 'upload'])
        ->middleware('permission:orders.create')
        ->name('orders.import.upload');

    Route::get('/import/{batch}', [OrderImportController::class, 'show'])
        ->middleware('permission:orders.create')
        ->where('batch', '[A-Za-z0-9-]+')
        ->name('orders.import.show');

    Route::post('/import/{batch}/process', [OrderImportController::class, 'process'])
        ->middleware('permission:orders.create')
        ->where('batch', '[A-Za-z0-9-]+')
        ->name('orders.import.process');

    Route::get('/catalog/suppliers', [OrderController::class, 'suppliers'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.suppliers');

    Route::get('/catalog/products', [OrderController::class, 'products'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.products');

    Route::get('/catalog/variants', [OrderController::class, 'variants'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.variants');

    Route::get('/catalog/after-hours', [OrderController::class, 'afterHours'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.after-hours');

    Route::get('/catalog/customer-lookup', [OrderController::class, 'customerLookup'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.customer-lookup');

    Route::post('/catalog/customer-ban', [OrderController::class, 'banCatalogCustomer'])
        ->middleware('permission:customers.bans.create')
        ->name('orders.catalog.customer-ban');

    Route::get('/catalog/duplicates', [OrderController::class, 'duplicatesPreview'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.duplicates');

    Route::get('/catalog/couriers', [OrderController::class, 'catalogCouriers'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.couriers');

    Route::get('/catalog/courier-districts', [OrderController::class, 'catalogCourierDistricts'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.courier-districts');

    Route::get('/catalog/courier-cities', [OrderController::class, 'catalogCourierCities'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.courier-cities');

    Route::get('/catalog/courier-fee-preview', [OrderController::class, 'catalogCourierFeePreview'])
        ->middleware('permission:orders.create|orders.update')
        ->name('orders.catalog.courier-fee-preview');

    Route::post('/bulk/assign', [OrderController::class, 'bulkAssignCca'])
        ->middleware('permission:orders.cca.assign')
        ->name('orders.bulk.assign');

    Route::post('/bulk/assign-random', [OrderController::class, 'randomAssignCca'])
        ->middleware('permission:orders.cca.assign')
        ->name('orders.bulk.assign-random');

    Route::post('/bulk/assign-random-allocations', [OrderController::class, 'randomAssignCcaAllocations'])
        ->middleware('permission:orders.cca.assign')
        ->name('orders.bulk.assign-random-allocations');

    Route::post('/bulk/pool', [OrderController::class, 'bulkMoveToPool'])
        ->middleware('permission:orders.cca.assign')
        ->name('orders.bulk.pool');

    Route::post('/bulk/unassign', [OrderController::class, 'bulkUnassign'])
        ->middleware('permission:orders.cca.assign')
        ->name('orders.bulk.unassign');

    Route::get('/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.show');

    Route::post('/{order}', [OrderController::class, 'update'])
        ->middleware('permission:orders.update')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.update');

    Route::post('/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('permission:orders.status.update')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.status.update');

    Route::post('/{order}/cca', [OrderController::class, 'assignCca'])
        ->middleware('permission:orders.cca.assign')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.cca.assign');

    Route::post('/{order}/cca/claim', [OrderController::class, 'claimFromPool'])
        ->middleware('permission:orders.update')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.cca.claim');

    Route::post('/{order}/cca/pool', [OrderController::class, 'moveToPool'])
        ->middleware('permission:orders.cca.assign')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.cca.pool');

    Route::post('/{order}/comments', [OrderController::class, 'storeComment'])
        ->middleware('permission:orders.comments.create')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.comments.store');

    Route::post('/{order}/customer/ban', [OrderController::class, 'banCustomer'])
        ->middleware('permission:customers.bans.create')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.customer.ban');

    Route::post('/{order}/payment/bank-transfer', [OrderController::class, 'submitBankTransfer'])
        ->middleware('permission:orders.update')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.payment.bank-transfer');

    Route::post('/{order}/discount', [OrderController::class, 'updateDiscount'])
        ->middleware('permission:orders.discount.update')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.discount.update');

    Route::get('/{order}/couriers', [OrderController::class, 'couriers'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.couriers');

    Route::get('/{order}/courier-services', [OrderController::class, 'courierServices'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.courier-services');

    Route::get('/{order}/courier-districts', [OrderController::class, 'courierDistricts'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.courier-districts');

    Route::get('/{order}/courier-cities', [OrderController::class, 'courierCities'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.courier-cities');

    Route::get('/{order}/courier-fee-preview', [OrderController::class, 'courierFeePreview'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.courier-fee-preview');

    Route::post('/{order}/shipment/book', [OrderController::class, 'bookShipment'])
        ->middleware('permission:orders.shipment.book')
        ->where('order', '[A-Za-z0-9-]+')
        ->name('orders.shipment.book');
});
