<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('app:make-module {name}')]
#[Description('Create a new application module')]
class MakeModuleCommand extends Command
{
    protected array $directories = [
            '',
            'Actions',
            'Database',
            'Database/Factories',
            'Database/Migrations',
            'Database/Seeders',
            'Events',
            'Filament',
            'Filament/Pages',
            'Filament/Resources',
            'Filament/Widgets',
            'Http',
            'Http/Controllers',
            'Http/Middleware',
            'Http/Requests',
            'Listeners',
            'Livewire',
            'Models',
            'Policies',
            'Providers',
            'Services',
            'Tests',
        ];
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = Str::studly($this->argument('name'));

        $basePath = app_path("Modules/{$module}");

        if (File::exists($basePath)) {
            $this->error("Module [{$module}] already exists.");

            return self::FAILURE;
        }


        // foreach ($directories as $directory) {
        //     mkdir($basePath . ($directory ? "/{$directory}" : ''), 0755, true);
        // }

        // file_put_contents(
        //     "{$basePath}/routes.php",
        //     "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n"
        // );

        // file_put_contents(
        //     "{$basePath}/README.md",
        //     "# {$module} Module\n\nModule documentation.\n"
        // );
        $this->createDirectories($basePath);
        $this->createRoutes($basePath, $module);
        $this->createReadme($basePath, $module);
        $this->createServiceProvider($basePath, $module);

        $this->info("Module [{$module}] created successfully.");

        return self::SUCCESS;
    }

    protected function createDirectories(string $basePath): void
    {
        foreach ($this->directories as $directory) {

            $path = $directory === ''
                ? $basePath
                : "{$basePath}/{$directory}";

            File::ensureDirectoryExists($path);

            File::put(
                "{$path}/.gitkeep",
                ''
            );
        }
    }

    protected function createReadme(string $basePath, string $module): void
    {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub('README')
        );

        File::put(
            "{$basePath}/README.md",
            $content
        );
    }

    protected function createRoutes(string $basePath, string $module): void
    {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub('routes')
        );

        File::put(
            "{$basePath}/routes.php",
            $content
        );
    }

    protected function createServiceProvider(string $basePath, string $module): void
    {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub('ModuleServiceProvider')
        );

        File::put(
            "{$basePath}/Providers/ModuleServiceProvider.php",
            $content
        );
    }
    
    protected function getStub(string $stub): string
    {
        return File::get(
            base_path("stubs/module/{$stub}.stub")
        );
    }
}
