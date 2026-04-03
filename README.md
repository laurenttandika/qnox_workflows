# qnox/workflows

Laravel workflow package for configurable approval flows with ordered levels, per-level track history, assignee resolution, and setup-driven actions.

## Install
```bash
composer require qnox/workflows
php artisan vendor:publish --tag=qnox-workflows-config
php artisan vendor:publish --tag=qnox-workflows-migrations
php artisan migrate
```

## How to Use
```bash
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

## Transition Driven Setup
Actions are configured in `workflow_transitions`. The engine does not generate fallback actions.

- Each transition defines `action_key`, `label`, `direction`, `to_level_id`, and optional `status`.
- `to_level_id` may be `null` for same-level or terminal actions.
- Use `status` to control the instance/history status after the action.
- Use `meta.complete = true` to explicitly mark an action as terminal.
- Use `meta.mark_submitted = true` to stamp `submitted_at` when needed.
- The first level can still be applicant-owned by using assignment criteria `['initiator' => true]`.

## Example Transition
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
```
