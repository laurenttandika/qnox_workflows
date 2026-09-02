<?php
namespace Qnox\Workflows\Services;
use Illuminate\Contracts\Auth\Authenticatable;
use Qnox\Workflows\Contracts\SupervisorResolver;
use Qnox\Workflows\Exceptions\WorkflowException;
class UnconfiguredSupervisorResolver implements SupervisorResolver
{
    public function resolve(Authenticatable $initiator, array $context = []): ?Authenticatable
    { throw new WorkflowException('No SupervisorResolver is configured. Bind one in the consuming application.'); }
}
