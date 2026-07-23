<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Models\WorkflowAssignment;

interface AssignmentProvider
{
    public function users(WorkflowAssignment $assignment, array $context = []): Collection;

    public function contains(
        Authenticatable $user,
        WorkflowAssignment $assignment,
        array $context = []
    ): bool;
}
