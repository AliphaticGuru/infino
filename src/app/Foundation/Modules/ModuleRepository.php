<?php

namespace App\Foundation\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ModuleRepository
{
    public function all(): Collection
    {
        $modules = collect();

        $path = app_path('Modules');

        if (! File::exists($path)) {
            return collect();
        }

        foreach (File::directories($path) as $directory) {

            $manifest = "{$directory}/module.json";

            if (! File::exists($manifest)) {
                continue;
            }

            $data = json_decode(
                File::get($manifest),
                true,
                flags: JSON_THROW_ON_ERROR
            );

            $modules->push(
                ModuleManifest::fromArray($data)
            );
        }

        return $modules;
    }
}