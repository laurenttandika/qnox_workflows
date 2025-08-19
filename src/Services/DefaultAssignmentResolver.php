<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Models\{WorkflowLevel, WorkflowAssignment};

class DefaultAssignmentResolver implements AssignmentResolver
{
    public function userEligibleForLevel(User $user, WorkflowLevel $level, array $context = []): bool
    {
        // 1) Direct user assignment via morph
        $direct = $level->assignments
            ->first(fn(WorkflowAssignment $a) => $a->assignable_type === get_class($user) && (int)$a->assignable_id === (int)$user->getAuthIdentifier());
        if ($direct) return true;

        // 2) Criteria-based (very generic). Example keys: permissions, department_id, designation_id, region_code.
        foreach ($level->assignments as $a) {
            $criteria = (array) ($a->criteria ?? []);

            // permissions: ["approve_invoices", "finance:review"]
            if (!empty($criteria['permissions'])) {
                $ok = collect($criteria['permissions'])->every(fn($p) => Gate::forUser($user)->allows($p, [$level, $context]));
                if ($ok) return true;
            }

            // property equals checks e.g., department_id, designation_id, region_code
            foreach (['department_id','designation_id','region_code','country'] as $prop) {
                if (array_key_exists($prop, $criteria)) {
                    if (data_get($user, $prop) == $criteria[$prop]) return true;
                }
            }
        }

        return false;
    }

    public function resolveAssignees(WorkflowLevel $level, array $context = []): Collection
    {
        $users = collect();
        $userClass = config('workflows.user_model');

        foreach ($level->assignments as $a) {
            if ($a->assignable && $a->assignable instanceof $userClass) {
                $users->push($a->assignable);
            }
            $criteria = (array) ($a->criteria ?? []);
            // Optional: allow explicit user_ids in criteria
            if (!empty($criteria['user_ids']) && class_exists($userClass)) {
                $users = $users->merge($userClass::query()->whereIn('id', (array)$criteria['user_ids'])->get());
            }
        }

        return $users->unique('id')->values();
    }
}