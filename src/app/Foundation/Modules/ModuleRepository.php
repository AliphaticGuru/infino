<?php

namespace App\Foundation\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class ModuleRepository
{
    public function __construct(
        private Filesystem $files,
    ) {
    }

    /**
     * @return Collection<int, ModuleManifest>
     */
    public function all(): Collection
    {
        $modules = collect();

        // $path = app_path('Modules');

        $path = config('modules.path', app_path('Modules'));

        if (! $this->files->exists($path)) {
            return collect();
        }

        foreach ($this->files->directories($path) as $directory) {

            $manifest = "{$directory}/module.json";

            if (! $this->files->exists($manifest)) {
                continue;
            }

            $data = json_decode(
                $this->files->get($manifest),
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