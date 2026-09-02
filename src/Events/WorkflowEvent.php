<?php

namespace Qnox\Workflows\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Qnox\Workflows\Models\WorkflowInstance;

abstract class WorkflowEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public ?Authenticatable $actor = null,
        public mixed $transition = null,
        public array $payload = [],
    ) {}
}
