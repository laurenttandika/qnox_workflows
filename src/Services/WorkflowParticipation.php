<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Models\WorkflowLevel;

class WorkflowParticipation
{
    public function __construct(
        protected ParticipantProviderRegistry $providers,
        protected AssignmentResolver $assignments,
    ) {}

    public function hasConfiguredParticipants(WorkflowLevel $level): bool
    {
        return $level->participants()->active()->exists();
    }

    public function usersForLevel(
        WorkflowLevel $level,
        string $capability = 'can_act',
        array $context = []
    ): Collection {
        $participants = $level->participants()
            ->active()
            ->where($capability, true)
            ->get();

        return $participants
            ->flatMap(function ($participant) use ($context) {
                return $this->providers->for($participant)?->users($participant, $context) ?? collect();
            })
            ->unique(fn ($user) => $this->key($user))
            ->values();
    }

    public function eligibleUsers(
        WorkflowLevel $level,
        array $context = [],
        string $capability = 'can_act'
    ): Collection
    {
        $routed = $this->assignments->resolveAssignees($level->loadMissing('assignments'), $context);

        if (!$this->hasConfiguredParticipants($level)) {
            return $routed->unique(fn ($user) => $this->key($user))->values();
        }

        $participants = $this->usersForLevel($level, $capability, $context);
        if ($routed->isEmpty()) {
            return $participants;
        }

        $routedKeys = $routed->mapWithKeys(fn ($user) => [$this->key($user) => true]);

        return $participants
            ->filter(fn ($user) => isset($routedKeys[$this->key($user)]))
            ->values();
    }

    public function can(
        Authenticatable $user,
        WorkflowLevel $level,
        string $capability,
        array $context = []
    ): bool {
        if (!$this->hasConfiguredParticipants($level)) {
            return $this->assignments->userEligibleForLevel($user, $level->loadMissing('assignments'), $context);
        }

        return $level->participants()
            ->active()
            ->where($capability, true)
            ->get()
            ->contains(fn ($participant) =>
                $this->providers->for($participant)?->contains($user, $participant, $context) === true
            );
    }

    protected function key(object $user): string
    {
        $type = method_exists($user, 'getMorphClass') ? $user->getMorphClass() : get_class($user);

        return $type.':'.$user->getAuthIdentifier();
    }
}
