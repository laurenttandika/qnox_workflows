<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Container\Container;
use Qnox\Workflows\Contracts\AssignmentProvider;
use Qnox\Workflows\Models\WorkflowAssignment;

class AssignmentProviderRegistry
{
    public function __construct(protected Container $container) {}

    public function for(WorkflowAssignment $assignment): ?AssignmentProvider
    {
        $type = $assignment->type ?: 'user';
        $provider = config("workflows.assignment_providers.{$type}");

        if (!$provider) {
            return null;
        }

        $resolved = $this->container->make($provider);

        return $resolved instanceof AssignmentProvider ? $resolved : null;
    }
}
