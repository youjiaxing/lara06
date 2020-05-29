<?php

namespace App\Providers;

use App\Services\CategoryService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(['products.index'], function (\Illuminate\View\View $view) {
            $service = app(CategoryService::class);
            $view->with('categoryTree', $service->getCategoryTree());
        });
    }
}
