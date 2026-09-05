<?php

use App\Http\Controllers\CallCenter\AgentUiController;
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

    /*
    |--------------------------------------------------------------------------
    | O1.1 UI preview — Call Center Agents
    | Temporary GET-only routes for UI review. No CRUD, no permission
    | middleware, and no persistence. Replace in O1.2/O1.3.
    |--------------------------------------------------------------------------
    */
    Route::prefix('call-center/agents')->group(function () {
        Route::get('/', [AgentUiController::class, 'index'])
            ->name('ui.call-center.agents.index');

        Route::get('/create', [AgentUiController::class, 'create'])
            ->name('ui.call-center.agents.create');

        Route::get('/{agent}', [AgentUiController::class, 'show'])
            ->where('agent', '[a-z0-9-]+')
            ->name('ui.call-center.agents.show');

        Route::get('/{agent}/edit', [AgentUiController::class, 'edit'])
            ->where('agent', '[a-z0-9-]+')
            ->name('ui.call-center.agents.edit');
    });

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
