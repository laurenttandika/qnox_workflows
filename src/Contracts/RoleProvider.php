<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Support\Collection;

interface RoleProvider
{
    /** @return Collection<int, array{value:string|int,label:string}> */
    public function options(?string $search = null): Collection;
}
