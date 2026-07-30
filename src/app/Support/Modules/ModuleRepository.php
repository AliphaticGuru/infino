<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\File;

class ModuleRepository
{
    public function all(): array
    {
        $modules = [];

        $path = app_path('Modules');

        if (! File::exists($path)) {
            return [];
        }

        foreach (File::directories($path) as $directory) {

            $manifest = "{$directory}/module.json";

            if (! File::exists($manifest)) {
                continue;
            }

            $modules[] = json_decode(
                File::get($manifest),
                true
            );
        }

        return $modules;
    }
}