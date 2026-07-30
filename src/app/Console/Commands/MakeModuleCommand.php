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
        $this->writeStub(
            'README',
            $module,
            "{$basePath}/README.md"
        );
    }

    protected function createRoutes(string $basePath, string $module): void
    {
        $this->files->ensureDirectoryExists("{$basePath}/routes");

        $this->writeStub(
            'web',
            $module,
            "{$basePath}/routes/web.php"
        );

        $this->writeStub(
            'api',
            $module,
            "{$basePath}/routes/api.php"
        );
    }

    protected function createServiceProvider(string $basePath, string $module): void
    {
        $this->writeStub(
            'ModuleServiceProvider',
            $module,
            "{$basePath}/Providers/{$module}ServiceProvider.php"
        );
    }

    protected function createManifest(string $basePath, string $module): void
    {
        $this->writeStub(
            'module',
            $module,
            "{$basePath}/module.json"
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

    protected function writeStub(
    string $stub,
    string $module,
    string $destination
    ): void {
        $content = str_replace(
            '{{ module }}',
            $module,
            $this->getStub($stub)
        );

        $this->files->put(
            $destination,
            $content
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