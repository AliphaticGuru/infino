<?php

namespace App\Foundation\Modules;

use InvalidArgumentException;

final readonly class ModuleManifest
{
    public function __construct(
        public string $name,
        public string $description,
        public string $version,
        public bool $enabled,
        public array $providers,
        public array $dependencies,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? throw new InvalidArgumentException('Module name is required.'),
            description: $data['description'] ?? '',
            version: $data['version'] ?? '1.0.0',
            enabled: $data['enabled'] ?? true,
            providers: $data['providers'] ?? [],
            dependencies: $data['dependencies'] ?? [],
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function providers(): array
    {
        return $this->providers;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function hasDependencies(): bool
    {
        return $this->dependencies !== [];
    }

    public function hasProvider(string $provider): bool
    {
        return $this->providers !== [];
    }

    public function dependsOn(string $module): bool
    {
        return in_array($module, $this->dependencies, true);
    }

    public function providersExist(): bool
    {
        return collect($this->providers)
            ->every(
                fn (string $provider) => class_exists($provider)
            );
    }
}