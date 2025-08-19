<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Support\Collection;
use Qnox\Workflows\Models\{WorkflowInstance, WorkflowLevel};

interface AssignmentResolver
{
    public function userEligibleForLevel(User $user, WorkflowLevel $level, array $context = []): bool;

    /** @return Collection<int, \Illuminate\Notifications\RoutesNotifications|User> */
    public function resolveAssignees(WorkflowLevel $level, array $context = []): Collection;
}