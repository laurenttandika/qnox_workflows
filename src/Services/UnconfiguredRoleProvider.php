<?php
namespace Qnox\Workflows\Services;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\{RoleAssigneeResolver, RoleProvider};
use Qnox\Workflows\Exceptions\WorkflowException;
class UnconfiguredRoleProvider implements RoleProvider, RoleAssigneeResolver
{
    public function options(?string $search = null): Collection { return collect(); }
    public function resolve(string|int $role, array $context = []): Collection
    { throw new WorkflowException('No RoleAssigneeResolver is configured. Bind one in the consuming application.'); }
}
