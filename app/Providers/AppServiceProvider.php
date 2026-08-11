<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Feeder\Core\Authorization\Services\MenuService;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layout_main.app', function ($view) {
            $user = auth()->user();

            $menu = $user
                ? app(MenuService::class)->getForUser($user)
                : new \Feeder\Core\Authorization\Menu\Menu();

            $view->with('menu', $menu);
        });
    }
}
