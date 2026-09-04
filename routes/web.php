<?php

use App\Http\Controllers\FileProxyController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Team\TeamTreeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/main/dashboard', function () {
        return view('pages.main.dashboard');
    })->name('dashboard');

    Route::prefix('team-structure')
        ->middleware('permission:team.structure.view')
        ->group(function () {
            Route::get('/', [TeamTreeController::class, 'index'])
                ->name('team.structure');

            Route::get('/root', [TeamTreeController::class, 'root'])
                ->name('team.structure.root');

            Route::get('/search', [TeamTreeController::class, 'search'])
                ->name('team.structure.search');

            Route::get('/nodes/{user}/children', [TeamTreeController::class, 'children'])
                ->name('team.structure.children');

            Route::get('/nodes/{user}/path', [TeamTreeController::class, 'path'])
                ->name('team.structure.path');
        });

    Route::get('/files/{uuid}/thumbnail/{size?}', [FileProxyController::class, 'thumbnail'])
        ->where([
            'uuid' => '[A-Za-z0-9]+',
            'size' => 'sm|md|lg',
        ])
        ->name('files.thumbnail');

    Route::get('/files/{uuid}/view', [FileProxyController::class, 'view'])
        ->where('uuid', '[A-Za-z0-9]+')
        ->name('files.view');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');

    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');

    Route::get('/products/{product}/details', [ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.details');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/registration.php';
require __DIR__ . '/auth.php';
