<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Support\Collection;

interface RoleAssigneeResolver
{
    public function resolve(string|int $role, array $context = []): Collection;
}
