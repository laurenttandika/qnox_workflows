<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Container\Container;
use Qnox\Workflows\Contracts\ParticipantProvider;
use Qnox\Workflows\Models\WorkflowLevelParticipant;

class ParticipantProviderRegistry
{
    public function __construct(protected Container $container) {}

    public function for(WorkflowLevelParticipant $participant): ?ParticipantProvider
    {
        $provider = config("workflows.participant_providers.{$participant->type}");
        if (!$provider) {
            return null;
        }

        $resolved = $this->container->make($provider);

        return $resolved instanceof ParticipantProvider ? $resolved : null;
    }
}
