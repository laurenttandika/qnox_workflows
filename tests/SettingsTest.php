<?php
namespace Qnox\Workflows\Tests;
use Qnox\Workflows\Tests\Fixtures\User;
class SettingsTest extends TestCase
{
    public function test_simple_editor_hides_advanced_configuration(): void
    {
        $user=User::create(['name'=>'Admin','is_active'=>true]);
        $response=$this->actingAs($user)->get('/settings/workflows/modules/hr.leave/workflows/create');
        $response->assertOk()->assertSee('Employee supervisor')->assertSee('Selected employees')
            ->assertDontSee('Criteria JSON')->assertDontSee('Transition')->assertDontSee('Assignment mode')->assertDontSee('Start level');
    }
    public function test_complete_payload_validation_rejects_conflicting_fields(): void
    {
        $user=User::create(['name'=>'Admin','is_active'=>true]);
        $this->actingAs($user)->from('/settings/workflows/modules/hr.leave/workflows/create')->post('/settings/workflows/workflows', [
            'module_key'=>'hr.leave','name'=>'Leave','is_active'=>1,
            'levels'=>[['name'=>'Manager','approver_type'=>'supervisor','approver_role'=>'manager','user_ids'=>[$user->id]]],
        ])->assertRedirect('/settings/workflows/modules/hr.leave/workflows/create')
          ->assertSessionHasErrors(['levels.0.approver_role','levels.0.user_ids']);
    }
}
