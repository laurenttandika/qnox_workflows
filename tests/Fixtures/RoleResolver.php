<?php
namespace Qnox\Workflows\Tests\Fixtures;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\RoleAssigneeResolver;
class RoleResolver implements RoleAssigneeResolver
{
    public array $users = [];
    public function resolve(string|int $role, array $context = []): Collection { return collect($this->users); }
}
