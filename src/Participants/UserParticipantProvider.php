<?php

namespace Qnox\Workflows\Participants;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\ParticipantProvider;
use Qnox\Workflows\Models\WorkflowLevelParticipant;

class UserParticipantProvider implements ParticipantProvider
{
    public function users(WorkflowLevelParticipant $participant, array $context = []): Collection
    {
        return $participant->participant ? collect([$participant->participant]) : collect();
    }

    public function contains(
        Authenticatable $user,
        WorkflowLevelParticipant $participant,
        array $context = []
    ): bool {
        return $this->users($participant, $context)->contains(
            fn ($candidate) => get_class($candidate) === get_class($user)
                && (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier()
        );
    }
}
