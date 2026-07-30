<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use App\Support\Modules\ModuleRepository;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerModules();
    }

    public function boot(): void
    {
        //
    }

    protected function registerModules(): void
    {
        // $repository = new ModuleRepository();
        $repository = app(ModuleRepository::class);

        foreach ($repository->all() as $module) 
        {

            if (! ($module['enabled'] ?? true)) {
                continue;
            }

            foreach ($module['providers'] as $provider) {

                if (class_exists($provider)) {
                    $this->app->register($provider);
                }
            }
        }
    }
}