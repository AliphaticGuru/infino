<?php

namespace App\Foundation\Modules;

use Illuminate\Contracts\Foundation\Application;

final readonly class ModuleManager
{
    public function __construct(
        private Application $app,
        private ModuleRepository $repository,
    ) {
    }

    public function load(): void
    {
        $this->repository
            ->all()
            ->filter->isEnabled()
            ->each(function (ModuleManifest $module): void {

                collect($module->providers())
                    ->filter(fn (string $provider) => class_exists($provider))
                    ->each(
                        fn (string $provider) => $this->app->register($provider)
                    );
            });
    }
}