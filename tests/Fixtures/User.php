<?php
namespace Qnox\Workflows\Tests\Fixtures;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable
{
    protected $table = 'test_users';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function can($abilities, $arguments = []) { return true; }
}
