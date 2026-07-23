# qnox/workflows

Laravel workflow package for configurable approval flows with ordered levels, per-level track history, assignee resolution, and setup-driven actions.

## Install
```bash
composer require qnox/workflows
php artisan vendor:publish --tag=qnox-workflows-config
php artisan vendor:publish --tag=qnox-workflows-migrations
php artisan vendor:publish --tag=qnox-workflows-views # only when overriding UI
php artisan migrate
```

After installation, the default settings dashboard is:

```text
/settings/workflows
```

The prefix, middleware, route names, API routes, and every package view are configurable
in `config/workflows.php`.

For production, add your authorization middleware to the settings route group:

```php
'routes' => [
    'web' => [
        'middleware' => ['web', 'auth', 'can:workflows.manage'],
    ],
],
```

Define the `workflows.manage` ability with a Laravel gate or create the equivalent
permission when using `spatie/laravel-permission`.

## Concepts
- `Workflow`: the whole process definition
- `WorkflowLevel`: a step inside the process
- `WorkflowAssignment`: who can act on a level
- `WorkflowTransition`: an allowed action from one level to another
- `WorkflowInstance`: one running record for a subject
- `WorkflowInstanceLevel`: track/history rows for the running instance
- `WorkflowAction`: the actions actually taken

## Subject Model
The workflow instance belongs to a polymorphic `subject`. The subject is the business resource being reviewed or approved.

Examples of a subject:
- `LeaveRequest`
- `PurchaseRequisition`
- `Invoice`
- `Tender`

The user is not the subject unless the thing being approved is actually a user record.

In this package:
- `subject` = the resource under workflow
- `initiator` = the user who started the workflow
- `actor` = the user who performs an action on a level

The package stores the subject through:
- `workflow_instances.subject_type`
- `workflow_instances.subject_id`

Example:
```php
$instance = app(WorkflowEngine::class)->start(
    $purchaseRequisition,   // subject
    $workflow,
    auth()->user(),         // initiator
    ['amount' => $purchaseRequisition->amount]
);
```

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

Return or reject to a specific previous level:
```php
WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $financeLevel->id,
    'to_level_id' => $applicantLevel->id,
    'action_key' => 'reject_to_applicant',
    'label' => 'Reject to Applicant',
    'direction' => 'backward',
    'status' => 'rejected',
]);

WorkflowTransition::create([
    'workflow_id' => $workflow->id,
    'from_level_id' => $financeLevel->id,
    'to_level_id' => $supervisorLevel->id,
    'action_key' => 'return_to_supervisor',
    'label' => 'Return to Supervisor',
    'direction' => 'backward',
    'status' => 'returned',
]);
```

This allows a level to send the workflow back to any earlier level, not only the immediately previous one.

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

Load the underlying subject resource from the instance:

```php
$subject = $instance?->subject;
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

- `GET /api/workflows/instances/{instance}/actions`
- `POST /api/workflows/instances/{instance}/act`
- `GET /api/workflow-instances/{instance}/actions`
- `POST /api/workflow-instances/{instance}/act`

The last two endpoints are v0 compatibility aliases and can be disabled with
`workflows.routes.api.legacy_routes`.

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

## Version 1 Administration

Version 1 provides web configuration screens for:

- Module groups
- Business modules
- Workflow definitions
- Ordered workflow levels
- Configured actions and transitions
- Number formats and sequences
- Workflow instance history and action modals
- Per-user workflow inboxes and sidebar counters

Add the settings link to a Blade menu:

```blade
@can('workflows.manage')
    <a href="{{ route('workflows.dashboard') }}">Workflow Settings</a>
@endcan
```

For applications with a menu registry:

```php
use Qnox\Workflows\Support\WorkflowMenu;

$menu->register(WorkflowMenu::items());
```

The package does not mutate the host application's navigation. This keeps it compatible
with Blade sidebars, AdminLTE, Spatie menus, Livewire navigation, and custom menu tables.

## Workflow Inbox and Sidebar Counters

The package materializes one `WorkflowInboxItem` for every resolved user when a workflow
enters a level. This makes counters fast and records whether each recipient opened or
responded to the assignment.

Default inbox:

```text
/workflows/inbox
```

Named links:

```text
workflows.inbox.index
workflows.inbox.new
workflows.inbox.pending
workflows.inbox.attended
workflows.inbox.responded
workflows.inbox.held
workflows.inbox.ended
workflows.inbox.counts
```

Categories mean:

- `new`: assigned but not yet opened
- `pending`: currently assigned and not yet answered
- `attended`: opened but not yet answered
- `responded`: the user performed a workflow action
- `held`: a workflow related to the user is currently on hold
- `ended`: a workflow related to the user has completed

Get all counters:

```php
use Qnox\Workflows\Services\WorkflowInbox;

$counts = app(WorkflowInbox::class)->counts(auth()->user());
```

Example:

```php
[
    'new' => 4,
    'pending' => 7,
    'attended' => 2,
    'responded' => 16,
    'held' => 1,
    'ended' => 42,
]
```

Add links directly to a Blade sidebar:

```blade
@php($workflowCounts = app(\Qnox\Workflows\Services\WorkflowInbox::class)->counts(auth()->user()))

<a href="{{ route('workflows.inbox.new') }}">
    New workflows
    @if($workflowCounts['new'])
        <span class="badge">{{ $workflowCounts['new'] }}</span>
    @endif
</a>

<a href="{{ route('workflows.inbox.responded') }}">
    Responded
    <span class="badge">{{ $workflowCounts['responded'] }}</span>
</a>
```

Or use the package menu descriptors:

```php
$items = WorkflowMenu::inbox(auth()->user());
```

For asynchronous sidebar updates:

```http
GET /workflows/inbox/counts
```

The response contains the six counter values as JSON. The default inbox is provided by
`workflows::inbox.index` and can be replaced:

```php
'views' => [
    'inbox' => 'my-application.workflows.inbox',
],
```

Approvers also receive `NextApproverNotification`. Configure its channels:

```php
'notify_channels' => ['mail', 'database'],
```

Mail notifications link to `workflows.inbox.show`; database notifications contain the
workflow instance, workflow, subject, current level, and status identifiers. Notifications
are queued after the workflow transaction commits.

Inbox routes have their own middleware configuration:

```php
'routes' => [
    'inbox' => [
        'enabled' => true,
        'prefix' => 'workflows/inbox',
        'middleware' => ['web', 'auth'],
    ],
],
```

## Modules and Groups

`WorkflowGroup` organizes related business functions. `WorkflowModule` represents a
business process, and `Workflow` represents one executable definition.

```php
$group = WorkflowGroup::create([
    'name' => 'Finance',
    'slug' => 'finance',
]);

$module = WorkflowModule::create([
    'workflow_group_id' => $group->id,
    'name' => 'Payment Requisitions',
    'slug' => 'payment-requisitions',
]);

$workflow = Workflow::create([
    'workflow_group_id' => $group->id,
    'workflow_module_id' => $module->id,
    'name' => 'Standard Payment Approval',
    'slug' => 'standard-payment-approval',
    'is_active' => true,
]);
```

A module may be attached to application configuration through `moduleable_type` and
`moduleable_id`, for example a `PaymentRequisitionType`.

## Model Integration

Add `HasWorkflows` to resources that participate in workflows:

```php
use Qnox\Workflows\Concerns\HasWorkflows;

class PaymentRequisition extends Model
{
    use HasWorkflows;
}
```

Then start and retrieve workflows through the resource:

```php
$instance = $requisition->startWorkflow(
    $workflow,
    auth()->user(),
    [
        'amount' => $requisition->amount,
        'department_id' => $requisition->department_id,
    ],
);

$current = $requisition->currentWorkflowInstance();
```

## Lifecycle Events

Version 1 dispatches:

- `WorkflowStarting`
- `WorkflowStarted`
- `WorkflowActioning`
- `WorkflowActioned`
- `WorkflowLevelEntered`
- `WorkflowLevelExited`
- `WorkflowCompleted`
- `WorkflowRejected`
- `WorkflowReturned`
- `WorkflowHeld`
- `WorkflowResumed`
- `WorkflowRecalled`

The `Starting` and `Actioning` events run synchronously and may stop the operation by
throwing an exception. All other events are registered after the workflow database
transaction commits.

Application-specific behavior belongs in listeners:

```php
use Qnox\Workflows\Events\WorkflowCompleted;

class MarkPaymentApproved
{
    public function handle(WorkflowCompleted $event): void
    {
        $payment = $event->instance->subject;

        if ($payment instanceof PaymentRequisition) {
            $payment->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }
}
```

Register it in the host application's event provider:

```php
protected $listen = [
    WorkflowCompleted::class => [
        MarkPaymentApproved::class,
    ],
];
```

This replaces the large module-ID `switch` subscriber used by IOO-WEB-V2.

## Polymorphic Assignments

Assignments may target any model:

```php
WorkflowAssignment::create([
    'workflow_level_id' => $level->id,
    'type' => 'position',
    'assignable_type' => Position::class,
    'assignable_id' => $position->id,
]);
```

The host application teaches the package how to resolve that model:

```php
use Qnox\Workflows\Contracts\AssignmentProvider;

class PositionAssignmentProvider implements AssignmentProvider
{
    public function users(WorkflowAssignment $assignment, array $context = []): Collection
    {
        return User::where('position_id', $assignment->assignable_id)->get();
    }

    public function contains(
        Authenticatable $user,
        WorkflowAssignment $assignment,
        array $context = []
    ): bool {
        return (string) $user->position_id === (string) $assignment->assignable_id;
    }
}
```

Register providers in the published configuration:

```php
'assignment_providers' => [
    'user' => UserAssignmentProvider::class,
    'position' => App\Workflows\PositionAssignmentProvider::class,
    'designation' => App\Workflows\DesignationAssignmentProvider::class,
    'unit' => App\Workflows\UnitAssignmentProvider::class,
    'department' => App\Workflows\DepartmentAssignmentProvider::class,
],
```

Expose the types in the settings form:

```php
'assignment_options' => [
    'user' => ['label' => 'User', 'model' => App\Models\User::class],
    'position' => ['label' => 'Position', 'model' => App\Models\Position::class],
    'designation' => ['label' => 'Designation', 'model' => App\Models\Designation::class],
    'unit' => ['label' => 'Unit', 'model' => App\Models\Department::class],
],
```

Use a morph map to avoid storing application class names:

```php
Relation::enforceMorphMap([
    'user' => User::class,
    'position' => Position::class,
    'unit' => Department::class,
    'payment-requisition' => PaymentRequisition::class,
]);
```

## Configurable Number Generation

Number sequences replace the IOO sysdef/table-name switch with editable formats and
atomic counters.

```php
NumberSequence::create([
    'key' => 'payment-requisition',
    'name' => 'Payment Requisition Number',
    'format' => '{prefix}/{year}/{number}',
    'prefix' => 'QNOX/PR',
    'next_value' => 1,
    'padding' => 6,
    'reset_period' => 'yearly',
    'is_active' => true,
]);
```

Generate a number:

```php
$number = app(NumberGenerator::class)->next('payment-requisition');
// QNOX/PR/2026/000001
```

Or use the model convenience trait:

```php
use Qnox\Workflows\Concerns\HasWorkflowNumber;

class PaymentRequisition extends Model
{
    use HasWorkflowNumber;
}

$number = $payment->generateWorkflowNumber('payment-requisition');
```

Supported format tokens are:

```text
{prefix} {number} {year} {year:2} {month} {day}
{module} {department} {unit} {tenant} {subject_id}
```

Custom tokens receive their values from context:

```php
$number = app(NumberGenerator::class)->next('department-document', [
    'department' => 'FIN',
    'module' => 'PR',
]);
```

Valid reset periods are `never`, `yearly`, `monthly`, and `daily`. Generation uses a
database transaction and `lockForUpdate()`, preventing duplicate numbers under
concurrent requests.

Scoped counters are supported:

```php
$number = app(NumberGenerator::class)->next(
    'payment-requisition',
    ['department' => $department->code],
    $department,
);
```

Create one `NumberSequence` record for each scope. The scope is stored polymorphically.

## Custom Views and Action Modals

Views load under the `workflows::` namespace. Override a screen without publishing:

```php
'views' => [
    'instance' => 'payments.workflows.show',
    'action_modal' => 'payments.workflows.action-modal',
],
```

Or publish all views:

```bash
php artisan vendor:publish --tag=qnox-workflows-views
```

Published files are written to:

```text
resources/views/vendor/workflows
```

Action modals are generated from `WorkflowTransition::form_schema`:

```php
'form_schema' => [
    'confirmation' => 'Approve and forward this request?',
    'fields' => [
        [
            'name' => 'comment',
            'type' => 'textarea',
            'label' => 'Comments',
            'required' => true,
        ],
    ],
],
```

To render package actions inside a custom resource view:

```blade
@include('workflows::actions.buttons', [
    'instance' => $instance,
    'actions' => app(WorkflowEngine::class)
        ->availableActions($instance, auth()->user()),
])
```

## Manual Testing Checklist

1. Open `/settings/workflows` and create a group, module, definition, levels, and transitions.
2. Create assignments for the start and approval levels.
3. Start a workflow using `startWorkflow()` or `WorkflowEngine::start()`.
4. Sign in as the resolved approver and open `/workflows/inbox/new`.
5. Opening the item moves it from `new` to `attended`.
6. Perform an action from the generated modal.
7. Confirm the item appears under `responded` and the next approver receives a `new` item.
8. Complete the workflow and confirm it appears under `ended`.
9. Create a number format under Workflow Settings → Number Formats.
10. Generate several numbers and confirm padding and reset behavior.
