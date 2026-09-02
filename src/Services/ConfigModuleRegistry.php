<?php

namespace Qnox\Workflows\Services;

use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\ModuleRegistry;

class ConfigModuleRegistry implements ModuleRegistry
{
    protected array $modules;

    public function __construct()
    {
        $this->modules = config('workflows.modules', []);
    }

    public function all(): Collection { return collect($this->modules); }
    public function has(string $key): bool { return array_key_exists($key, $this->modules); }
    public function label(string $key): ?string { return $this->modules[$key] ?? null; }
    public function register(string $key, string $label): void { $this->modules[$key] = $label; }
}
