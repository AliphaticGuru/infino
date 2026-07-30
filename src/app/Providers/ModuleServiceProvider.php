<?php

namespace App\Providers;

// use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
// use App\Foundation\Modules\ModuleManifest;
use App\Foundation\Modules\ModuleLoader;
use App\Foundation\Modules\ModuleRepository;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRepository::class);

        $this->app->singleton(ModuleLoader::class);

        $this->app
            ->make(ModuleLoader::class)
            ->load();
    }

    public function boot(): void
    {
        //
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