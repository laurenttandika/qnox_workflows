<?php

namespace Qnox\Workflows\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface UserProvider
{
    /** @return Collection<int, array{value:string|int,label:string}> */
    public function options(?string $search = null): Collection;
    public function findMany(array $ids): Collection;
    public function isEligible(Authenticatable $user): bool;
}
