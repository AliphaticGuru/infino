<?php

namespace App\Modules\Test\Providers;

use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadModuleRoutes();

        $this->loadMigrationsFrom(
            dirname(__DIR__) . '/Database/Migrations'
        );
    }

    protected function loadModuleRoutes(): void
    {
        $basePath = dirname(__DIR__);

        foreach (['web', 'api'] as $route) {

            $file = "{$basePath}/routes/{$route}.php";

            if (file_exists($file)) {
                $this->loadRoutesFrom($file);
            }
        }
    }
}