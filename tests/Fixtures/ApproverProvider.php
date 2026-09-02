<?php
namespace Qnox\Workflows\Tests\Fixtures;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\{RoleProvider, UserProvider};
class ApproverProvider implements RoleProvider, UserProvider
{
    public function options(?string $search = null): Collection { return collect([['value' => 'manager', 'label' => 'Manager']]); }
    public function findMany(array $ids): Collection { return User::whereKey($ids)->get(); }
    public function isEligible(Authenticatable $user): bool { return $user->is_active; }
}
