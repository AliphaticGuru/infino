<?php

declare(strict_types=1);

namespace App\Providers;

// use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
// use App\Foundation\Modules\ModuleManifest;
use App\Foundation\Modules\ModuleManager;
use App\Foundation\Modules\ModuleRepository;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRepository::class);

        $this->app->singleton(ModuleManager::class);

        // $this->app
        //     ->make(ModuleManager::class)
        //     ->load();
    }

    public function boot(ModuleManager $modules): void
    {
        $modules->load();
    }

    // protected function registerModules(): void
    // {
        // $repository = app(ModuleRepository::class);

        // foreach ($repository->all() as $module) 
        // {

        //     if (! $module->isEnabled()) {
        //         continue;
        //     }

        //     foreach ($module->providers() as $provider) {

        //         if (class_exists($provider)) {
        //             $this->app->register($provider);
        //         }
        //     }
        // }
        // $repository = app(ModuleRepository::class);

        // $repository
        //     ->all()
        //     ->filter->isEnabled()
        //     ->each(
        //         fn (ModuleManifest $module) => $module->registerProviders($this->app)
        // );
    // }
}