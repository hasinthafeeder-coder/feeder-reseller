<?php

use App\Http\Controllers\CallCenter\AgentUiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\FinancialOverviewController;
use App\Http\Controllers\FileProxyController;
use App\Http\Controllers\Meeting\MeetingController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationLiveController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Team\TeamTreeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/main/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('notifications')
        ->name('notifications.')
        ->middleware('permission:notifications.view')
        ->group(function () {
            Route::get('/', [NotificationController::class, 'index'])
                ->name('index');

            Route::get('/live', NotificationLiveController::class)
                ->name('live');

            Route::get('/{notification}', [NotificationController::class, 'show'])
                ->where('notification', '[A-Za-z0-9]+')
                ->name('show');

            Route::post('/{notification}/read', [NotificationController::class, 'markRead'])
                ->where('notification', '[A-Za-z0-9]+')
                ->name('read');
        });

    Route::prefix('meetings')
        ->name('meetings.')
        ->middleware('permission:meetings.view')
        ->group(function () {
            Route::get('/', [MeetingController::class, 'index'])
                ->name('index');

            Route::get('/{meeting}/qr-download', [MeetingController::class, 'downloadQr'])
                ->where('meeting', '[A-Za-z0-9]+')
                ->name('qr.download');

            Route::get('/{meeting}', [MeetingController::class, 'show'])
                ->where('meeting', '[A-Za-z0-9]+')
                ->name('show');
        });

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

    Route::get('/files/{uuid}/download', [FileProxyController::class, 'download'])
        ->where('uuid', '[A-Za-z0-9]+')
        ->name('files.download');

    /*
    |--------------------------------------------------------------------------
    | Call Center Agents
    | List / Profile are production reads (O1.4-D1).
    | Create is a production write (O1.4-D2).
    | Edit is a production write (O1.4-D3).
    | Activate / Deactivate are production writes (O1.4-D4).
    | Commission update is a production write (O1.4-D5-A).
    | Permission update is a production write (O1.4-D5-B).
    |--------------------------------------------------------------------------
    */
    Route::prefix('call-center/agents')->group(function () {
        Route::get('/', [AgentUiController::class, 'index'])
            ->middleware('permission:call_center.agents.view')
            ->name('ui.call-center.agents.index');

        Route::get('/create', [AgentUiController::class, 'create'])
            ->middleware('permission:call_center.agents.create')
            ->name('ui.call-center.agents.create');

        Route::post('/', [AgentUiController::class, 'store'])
            ->middleware('permission:call_center.agents.create')
            ->name('ui.call-center.agents.store');

        Route::get('/{agent}', [AgentUiController::class, 'show'])
            ->middleware('permission:call_center.agents.view')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.show');

        Route::get('/{agent}/edit', [AgentUiController::class, 'edit'])
            ->middleware('permission:call_center.agents.update')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.edit');

        Route::match(['put', 'patch'], '/{agent}', [AgentUiController::class, 'update'])
            ->middleware('permission:call_center.agents.update')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.update');

        Route::post('/{agent}/activate', [AgentUiController::class, 'activate'])
            ->middleware('permission:call_center.agents.activate')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.activate');

        Route::post('/{agent}/deactivate', [AgentUiController::class, 'deactivate'])
            ->middleware('permission:call_center.agents.deactivate')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.deactivate');

        Route::post('/{agent}/commission', [AgentUiController::class, 'updateCommission'])
            ->middleware('permission:call_center.agents.commission.update')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.commission.update');

        Route::post('/{agent}/permissions', [AgentUiController::class, 'updatePermissions'])
            ->middleware('permission:call_center.agents.permissions.update')
            ->where('agent', '[A-Za-z0-9-]+')
            ->name('ui.call-center.agents.permissions.update');
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

    Route::get('/finance/overview', [FinancialOverviewController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('finance.overview');

    require __DIR__ . '/settlements.php';
    require __DIR__ . '/orders.php';
});

require __DIR__ . '/registration.php';
require __DIR__ . '/auth.php';
