<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface SupervisorResolver
{
    public function resolve(Authenticatable $initiator, array $context = []): ?Authenticatable;
}
