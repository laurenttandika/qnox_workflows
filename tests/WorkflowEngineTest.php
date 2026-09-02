<?php
namespace Qnox\Workflows\Tests;
use Illuminate\Support\Facades\Event;
use Qnox\Workflows\Events\{ApprovalLevelEntered, WorkflowApproved, WorkflowRejected, WorkflowStarted};
use Qnox\Workflows\Exceptions\{WorkflowConflictException, WorkflowException};
use Qnox\Workflows\Models\{Workflow, WorkflowInstance};
use Qnox\Workflows\Services\WorkflowEngine;
use Qnox\Workflows\Tests\Fixtures\{Subject, User};
class WorkflowEngineTest extends TestCase
{
    public function test_supervisor_workflow_starts_with_snapshot_and_approves(): void
    {
        Event::fake(); [$initiator,$manager] = $this->users(); $this->supervisors()->supervisor = $manager;
        $workflow = Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'leave','is_active'=>true]);
        $workflow->levels()->create(['name'=>'Manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>true]);
        $instance = app(WorkflowEngine::class)->start(Subject::create(['name'=>'Leave 1']), $workflow, $initiator);
        $this->assertSame('in_progress', $instance->status);
        $this->assertDatabaseHas('workflow_instance_approvers', ['approver_id'=>(string)$manager->id,'status'=>'pending']);
        $approved = app(WorkflowEngine::class)->approve($instance, $manager, 'ok');
        $this->assertSame('approved', $approved->status); $this->assertNotNull($approved->approved_at);
        Event::assertDispatched(WorkflowStarted::class); Event::assertDispatched(ApprovalLevelEntered::class); Event::assertDispatched(WorkflowApproved::class);
    }
    public function test_selected_user_approval_advances_exactly_one_level_then_rejects(): void
    {
        Event::fake(); [$initiator,$first,$second] = $this->users(3); $this->roles()->users = [$second];
        $workflow = Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'mixed','is_active'=>true]);
        $one=$workflow->levels()->create(['name'=>'Review','sequence'=>1,'approver_type'=>'users','rejection_comment_required'=>true]);$one->selectedUsers()->create(['user_id'=>$first->id]);
        $two=$workflow->levels()->create(['name'=>'Final','sequence'=>2,'approver_type'=>'role','approver_role'=>'manager','rejection_comment_required'=>true]);
        $engine=app(WorkflowEngine::class);$instance=$engine->start(Subject::create(['name'=>'Leave 2']),$workflow,$initiator);
        $instance=$engine->approve($instance,$first);$this->assertSame($two->id,$instance->current_level_id);$this->assertSame('in_progress',$instance->status);
        $instance=$engine->reject($instance,$second,'not approved');$this->assertSame('rejected',$instance->status);$this->assertNotNull($instance->rejected_at);
        $this->assertCount(2,$instance->history);$this->assertDatabaseMissing('workflow_instance_levels',['workflow_instance_id'=>$instance->id,'level_sequence'=>3]);
        $this->assertDatabaseMissing('workflow_inbox_items',['workflow_instance_id'=>$instance->id,'ended_at'=>null]); Event::assertDispatched(WorkflowRejected::class);
    }
    public function test_self_approval_and_missing_approvers_leave_no_partial_instance(): void
    {
        [$initiator] = $this->users(); $this->supervisors()->supervisor = $initiator;
        $workflow=Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'none','is_active'=>true]);$workflow->levels()->create(['name'=>'Manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>true]);
        try { app(WorkflowEngine::class)->start(Subject::create(['name'=>'Leave']),$workflow,$initiator); $this->fail('Expected resolver failure.'); } catch (WorkflowException $e) { $this->assertSame(0,WorkflowInstance::count()); }
    }
    public function test_rejection_comment_and_duplicate_decisions_are_enforced(): void
    {
        [$initiator,$manager]=$this->users();$this->supervisors()->supervisor=$manager;$workflow=Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'conflict','is_active'=>true]);$workflow->levels()->create(['name'=>'Manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>true]);$engine=app(WorkflowEngine::class);$instance=$engine->start(Subject::create(['name'=>'Leave']),$workflow,$initiator);
        $this->expectException(WorkflowException::class);$engine->reject($instance,$manager);
    }
    public function test_terminal_instance_rejects_duplicate_decision(): void
    {
        [$initiator,$manager]=$this->users();$this->supervisors()->supervisor=$manager;$workflow=Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'duplicate','is_active'=>true]);$workflow->levels()->create(['name'=>'Manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>false]);$engine=app(WorkflowEngine::class);$instance=$engine->start(Subject::create(['name'=>'Leave']),$workflow,$initiator);$engine->approve($instance,$manager);
        $this->expectException(WorkflowConflictException::class);$engine->approve($instance,$manager);
    }
    public function test_definition_edits_do_not_change_runtime_snapshot(): void
    {
        [$initiator,$manager]=$this->users();$this->supervisors()->supervisor=$manager;
        $workflow=Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'snapshot','is_active'=>true]);
        $level=$workflow->levels()->create(['name'=>'Original manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>true]);
        $instance=app(WorkflowEngine::class)->start(Subject::create(['name'=>'Leave']),$workflow,$initiator);
        $level->update(['name'=>'Renamed manager']);
        $this->assertSame('Original manager',$instance->fresh()->history()->first()->level_name);
        $this->assertTrue(app(WorkflowEngine::class)->canApprove($instance->fresh(),$manager));
    }
    public function test_inactive_snapshotted_approver_cannot_act_and_initiator_can_cancel(): void
    {
        Event::fake();[$initiator,$manager]=$this->users();$this->supervisors()->supervisor=$manager;
        $workflow=Workflow::create(['module_key'=>'hr.leave','name'=>'Leave','slug'=>'cancel','is_active'=>true]);
        $workflow->levels()->create(['name'=>'Manager','sequence'=>1,'approver_type'=>'supervisor','rejection_comment_required'=>false]);
        $engine=app(WorkflowEngine::class);$instance=$engine->start(Subject::create(['name'=>'Leave']),$workflow,$initiator);
        $manager->update(['is_active'=>false]);$this->assertFalse($engine->canApprove($instance->fresh(),$manager));
        $cancelled=$engine->cancel($instance,$initiator,'withdrawn');$this->assertSame('cancelled',$cancelled->status);$this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('workflow_actions',['workflow_instance_id'=>$instance->id,'action'=>'cancel']);
    }
    private function users(int $count=2): array { return collect(range(1,$count))->map(fn($i)=>User::create(['name'=>'User '.$i,'is_active'=>true]))->all(); }
}
