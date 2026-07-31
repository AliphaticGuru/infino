<?php

declare(strict_types=1);

namespace App\Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerModule();
    }

    public function boot(): void
    {
        $this->bootModule();
        
        $this->loadModuleRoutes();

        $this->loadModuleMigrations();
    }

    protected function registerModule(): void
    {
        //
    }

    protected function bootModule(): void
    {
        //
    }

    protected function loadModuleRoutes(): void
    {
        $basePath = dirname((new \ReflectionClass($this))->getFileName());

        $routesPath = dirname($basePath) . '/routes';

        foreach (['web', 'api'] as $route) {

            $file = "{$routesPath}/{$route}.php";

            if (is_file($file)) {
                $this->loadRoutesFrom($file);
            }
        }
    }

    protected function loadModuleMigrations(): void
    {
        $path = dirname(
            dirname(
                (new \ReflectionClass($this))->getFileName()
            )
        ) . '/Database/Migrations';

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}