<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Signature('app:make-module {name}')]
#[Description('Create a new application module')]
class MakeModuleCommand extends Command
{
    protected array $directories = [
        '',
        'Contracts',

        'Database',
        'Database/Factories',
        'Database/Migrations',
        'Database/Seeders',

        'Http',
        'Http/Controllers',
        'Http/Middleware',
        'Http/Requests',

        'Models',
        'Policies',
        'Providers',
        'Services',
        'Support',

        'Tests',

        'routes',
    ];
/**
     * Execute the console command.
     */

    public function __construct(
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->normalizeName(
            $this->argument('name')
        );

        $basePath = $this->modulePath($module);

        if ($this->files->exists($basePath)) {
            $this->components->error(
                "Module [{$module}] already exists."
            );

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
        
        $this->createManifest($basePath, $module);

        $this->displaySuccess($module);
        
        // $this->info("Module [{$module}] created successfully.");

        return self::SUCCESS;
    }

    protected function createDirectories(string $basePath): void
    {
        foreach ($this->directories as $directory) {

            $path = $directory === ''
                ? $basePath
                : "{$basePath}/{$directory}";

            $this->files->ensureDirectoryExists($path);

            $this->files->put(
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

        $this->files->put(
            "{$basePath}/README.md",
            $content
        );
    }

    protected function createRoutes(string $basePath, string $module): void
    {
        $this->files->ensureDirectoryExists("{$basePath}/routes");

        foreach (['web', 'api'] as $route) {
            $content = str_replace(
                '{{ module }}',
                $module,
                $this->getStub($route)
            );

            $this->files->put(
                "{$basePath}/routes/{$route}.php",
                $content
            );
        }
    }

    protected function createServiceProvider(string $basePath, string $module): void
    {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub('ModuleServiceProvider')
        );

        $this->files->put(
            "{$basePath}/Providers/{$module}ServiceProvider.php",
            $content
        );
    }

    protected function createManifest(string $basePath, string $module): void
    {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub('module')
        );

        $this->files->put(
            "{$basePath}/module.json",
            $content
        );
    }

    protected function normalizeName(string $name): string
    {
        return Str::studly(trim($name));
    }
    
    protected function getStub(string $stub): string
    {
        return $this->files->get(
            base_path("stubs/module/{$stub}.stub")
        );
    }

    protected function modulePath(string $module): string
    {
        return app_path("Modules/{$module}");
    }

    protected function displaySuccess(string $module): void
    {
        $this->components->info(
            "Module [{$module}] created successfully."
        );
    }
}