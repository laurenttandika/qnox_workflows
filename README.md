# qnox/workflows

Laravel workflow package for configurable approval flows with ordered levels, per-level track history, assignee resolution, and setup-driven actions.

## Install
```bash
composer require qnox/workflows
php artisan vendor:publish --tag=qnox-workflows-config
php artisan vendor:publish --tag=qnox-workflows-migrations
php artisan migrate
```

## Concepts
- `Workflow`: the whole process definition
- `WorkflowLevel`: a step inside the process
- `WorkflowAssignment`: who can act on a level
- `WorkflowTransition`: an allowed action from one level to another
- `WorkflowInstance`: one running record for a subject
- `WorkflowInstanceLevel`: track/history rows for the running instance
- `WorkflowAction`: the actions actually taken

## Basic Usage
```php
use Qnox\Workflows\Services\WorkflowEngine;
use Qnox\Workflows\Models\Workflow;

$engine = app(WorkflowEngine::class);
$workflow = Workflow::where('slug', 'application-approval')->firstOrFail();

$instance = $engine->start($application, $workflow, auth()->user(), [
    'amount' => 5000000,
    'country' => 'TZ',
]);

$actions = $engine->availableActions($instance, auth()->user());
$engine->act($instance, 'submit', auth()->user(), ['comment' => 'Submitted to supervisor']);
```

## Setup a Workflow
Create a workflow, then define levels, assignments, and transitions.

```php
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Models\WorkflowAssignment;
use Qnox\Workflows\Models\WorkflowTransition;

$workflow = Workflow::create([
    'workflow_group_id' => 1,
    'name' => 'Application Approval',
    'slug' => 'application-approval',
    'is_active' => true,
]);

$applicantLevel = WorkflowLevel::create([
    'workflow_id' => $workflow->id,
    'name' => 'Applicant',
    'sequence' => 1,
    'is_start' => true,
    'description' => 'Application owner prepares and submits',
]);

$supervisorLevel = WorkflowLevel::create([
    'workflow_id' => $workflow->id,
    'name' => 'Supervisor Review',
    'sequence' => 2,
    'description' => 'Supervisor reviews the application',
    'is_approval' => true,
]);

$financeLevel = WorkflowLevel::create([
    'workflow_id' => $workflow->id,
    'name' => 'Finance Approval',
    'sequence' => 3,
    'description' => 'Finance approves and closes',
    'is_terminal' => true,
    'can_close' => true,
]);
```

## Setup Assignments
Assignments decide who is allowed to act on a level.

Applicant-owned first level:
```php
WorkflowAssignment::create([
    'workflow_level_id' => $applicantLevel->id,
    'criteria' => ['initiator' => true],
]);
```

Direct user assignment:
```php
WorkflowAssignment::create([
    'workflow_level_id' => $supervisorLevel->id,
    'assignable_type' => App\Models\User::class,
    'assignable_id' => 12,
]);
```

Criteria-based assignment:
```php
WorkflowAssignment::create([
    'workflow_level_id' => $financeLevel->id,
    'criteria' => [
        'department_id' => 3,
        'permissions' => ['payments.approve'],
    ],
]);
```

## Setup Transitions
Actions are configured in `workflow_transitions`. The engine does not generate fallback actions.

- Each transition defines `action_key`, `label`, `direction`, `to_level_id`, and optional `status`.
- `to_level_id` may be `null` for same-level or terminal actions.
- Use `status` to control the instance/history status after the action.
- Use `meta.complete = true` to explicitly mark an action as terminal.
- Use `meta.mark_submitted = true` to stamp `submitted_at` when needed.
- The first level can still be applicant-owned by using assignment criteria `['initiator' => true]`.

Example transitions:
```php
WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $applicantLevel->id,
    'to_level_id' => $supervisorLevel->id,
    'action_key' => 'submit',
    'label' => 'Submit',
    'direction' => 'forward',
    'status' => 'in_progress',
    'meta' => ['mark_submitted' => true],
]);

WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $supervisorLevel->id,
    'to_level_id' => $financeLevel->id,
    'action_key' => 'approve',
    'label' => 'Approve',
    'direction' => 'forward',
    'status' => 'approved',
]);

WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $supervisorLevel->id,
    'to_level_id' => $applicantLevel->id,
    'action_key' => 'return',
    'label' => 'Return for Update',
    'direction' => 'backward',
    'status' => 'returned',
]);

WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $financeLevel->id,
    'to_level_id' => null,
    'action_key' => 'complete',
    'label' => 'Complete',
    'direction' => 'stay',
    'status' => 'completed',
    'meta' => ['complete' => true],
]);
```

## Retrieve Configured Flows
Get all workflow definitions:

```php
use Qnox\Workflows\Models\Workflow;

$workflows = Workflow::query()
    ->with(['levels.assignments', 'transitions'])
    ->where('is_active', true)
    ->orderBy('name')
    ->get();
```

Get one workflow with its full setup:

```php
$workflow = Workflow::query()
    ->with([
        'levels.assignments',
        'levels.outgoingTransitions',
        'transitions',
    ])
    ->where('slug', 'application-approval')
    ->firstOrFail();
```

Get the ordered level flow:

```php
$levels = $workflow->levels()
    ->with(['assignments', 'outgoingTransitions.toLevel'])
    ->orderBy('sequence')
    ->get();
```

## Retrieve the Current Flow
Get the active workflow instance for a subject:

```php
use Qnox\Workflows\Models\WorkflowInstance;

$instance = WorkflowInstance::query()
    ->with(['workflow', 'currentLevel', 'history.level', 'actions'])
    ->where('subject_type', $application::class)
    ->where('subject_id', $application->getKey())
    ->latest('id')
    ->first();
```

Get the current level of that instance:

```php
$currentLevel = $instance?->currentLevel;
$currentStatus = $instance?->status;
```

Get the current track/history row:

```php
$currentTrack = $instance?->history()
    ->whereNull('exited_at')
    ->latest('id')
    ->first();
```

Get the actions available to the current user:

```php
$actions = app(WorkflowEngine::class)->availableActions($instance, auth()->user());
```

## Act on the Current Flow
Run a configured action:

```php
$updated = app(WorkflowEngine::class)->act(
    $instance,
    'approve',
    auth()->user(),
    ['comment' => 'Reviewed and approved']
);
```

## API Routes
The package registers:

- `GET /api/workflow-instances/{instance}/actions`
- `POST /api/workflow-instances/{instance}/act`

Example POST payload:

```json
{
  "action_key": "approve",
  "payload": {
    "comment": "Looks good"
  }
}
```

## Status Notes
Statuses are stored on `workflow_instances.status`, `workflow_instance_levels.status`, and `workflow_actions.status`.

Common values are:
- `pending`
- `in_progress`
- `approved`
- `returned`
- `rejected`
- `on_hold`
- `recalled`
- `completed`

You may also use your own status values in transitions if your application needs different labels.
