<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Support\Collection;

interface ModuleRegistry
{
    public function all(): Collection;
    public function has(string $key): bool;
    public function label(string $key): ?string;
    public function register(string $key, string $label): void;
}
