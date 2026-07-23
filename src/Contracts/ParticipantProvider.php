<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Models\WorkflowLevelParticipant;

interface ParticipantProvider
{
    public function users(WorkflowLevelParticipant $participant, array $context = []): Collection;

    public function contains(
        Authenticatable $user,
        WorkflowLevelParticipant $participant,
        array $context = []
    ): bool;
}
