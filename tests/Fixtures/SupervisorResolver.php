<?php
namespace Qnox\Workflows\Tests\Fixtures;
use Illuminate\Contracts\Auth\Authenticatable;
use Qnox\Workflows\Contracts\SupervisorResolver as Contract;
class SupervisorResolver implements Contract
{
    public ?Authenticatable $supervisor = null;
    public function resolve(Authenticatable $initiator, array $context = []): ?Authenticatable { return $this->supervisor; }
}
