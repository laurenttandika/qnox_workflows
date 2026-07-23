<?php

namespace Qnox\Workflows\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowTransition;

abstract class WorkflowEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public ?Authenticatable $actor = null,
        public ?WorkflowTransition $transition = null,
        public array $payload = [],
    ) {}
}
