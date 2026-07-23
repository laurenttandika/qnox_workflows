<?php

namespace Qnox\Workflows\Assignments;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\AssignmentProvider;
use Qnox\Workflows\Models\WorkflowAssignment;

class UserAssignmentProvider implements AssignmentProvider
{
    public function users(WorkflowAssignment $assignment, array $context = []): Collection
    {
        $userClass = config('workflows.user_model');
        $users = collect();

        if ($assignment->assignable_type === $userClass && $assignment->assignable) {
            $users->push($assignment->assignable);
        }

        $ids = (array) data_get($assignment->criteria, 'user_ids', []);
        if ($ids && class_exists($userClass)) {
            $users = $users->merge($userClass::query()->whereKey($ids)->get());
        }

        if (data_get($assignment->criteria, 'initiator')
            && data_get($context, 'initiator.type') === $userClass
            && data_get($context, 'initiator.id')) {
            $initiator = $userClass::query()->find(data_get($context, 'initiator.id'));
            if ($initiator) {
                $users->push($initiator);
            }
        }

        return $users->unique(fn ($user) => $user->getAuthIdentifier())->values();
    }

    public function contains(
        Authenticatable $user,
        WorkflowAssignment $assignment,
        array $context = []
    ): bool {
        return $this->users($assignment, $context)
            ->contains(fn ($candidate) =>
                get_class($candidate) === get_class($user)
                && (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            );
    }
}
