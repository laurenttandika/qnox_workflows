<?php

namespace Qnox\Workflows\Services\Guards;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Support\Facades\Gate;
use Qnox\Workflows\Models\WorkflowInstance;

class GuardEvaluator
{
    /**
     * Supported shapes:
     * - null or [] => pass
     * - {"gate": "policy-name"}
     * - {"equals": {"context.country": "TZ"}}
     * - {"gt": {"context.amount": 1000000}}
     * - {"in": {"context.status": ["A","B"]}}
     */
    public function passes($guard, WorkflowInstance $instance, ?User $actor = null, array $payload = []): bool
    {
        if (!$guard) return true;
        if (!is_array($guard)) return true; // ignore unknown formats gracefully

        if (isset($guard['gate'])) {
            return Gate::forUser($actor)->allows($guard['gate'], [$instance, $payload]);
        }

        foreach (['equals', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in'] as $op) {
            if (!isset($guard[$op]) || !is_array($guard[$op])) continue;
            foreach ($guard[$op] as $path => $expected) {
                $actual = $this->valueFrom($path, $instance, $actor, $payload);
                switch ($op) {
                    case 'equals':
                        if ($actual != $expected) return false;
                        break;
                    case 'gt':
                        if (!($actual > $expected)) return false;
                        break;
                    case 'gte':
                        if (!($actual >= $expected)) return false;
                        break;
                    case 'lt':
                        if (!($actual < $expected)) return false;
                        break;
                    case 'lte':
                        if (!($actual <= $expected)) return false;
                        break;
                    case 'in':
                        if (!in_array($actual, (array)$expected, true)) return false;
                        break;
                    case 'not_in':
                        if (in_array($actual, (array)$expected, true)) return false;
                        break;
                }
            }
        }

        return true;
    }

    private function valueFrom(string $path, WorkflowInstance $instance, $actor, array $payload)
    {
        return match (true) {
            str_starts_with($path, 'context.') => data_get($instance->context, substr($path, 8)),
            str_starts_with($path, 'subject.') => data_get($instance->subject, substr($path, 8)),
            str_starts_with($path, 'payload.') => data_get($payload, substr($path, 8)),
            str_starts_with($path, 'actor.')   => data_get($actor, substr($path, 6)),
            default => data_get($instance, $path),
        };
    }
}
