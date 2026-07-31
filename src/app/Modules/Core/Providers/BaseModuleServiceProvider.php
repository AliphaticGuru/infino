<?php

declare(strict_types=1);

namespace App\Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    final public function register(): void
    {
        $this->registerModule();
    }

    final public function boot(): void
    {
        $this->bootModule();

        $this->loadModuleRoutes();
        $this->loadModuleMigrations();
    }

    /**
     * Override this in child modules if needed.
     */
    protected function registerModule(): void
    {
        //
    }

    /**
     * Override this in child modules if needed.
     */
    protected function bootModule(): void
    {
        //
    }

    protected function loadModuleRoutes(): void
    {
        foreach (['web', 'api'] as $route) {

            $file = $this->modulePath("routes/{$route}.php");

            if (is_file($file)) {
                $this->loadRoutesFrom($file);
            }
        }
    }

    protected function loadModuleMigrations(): void
    {
        $path = $this->modulePath('Database/Migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $module = dirname(
            (new ReflectionClass($this))->getFileName(),
        );

        $module = dirname($module);

        return $path === ''
            ? $module
            : "{$module}/{$path}";
    }
}