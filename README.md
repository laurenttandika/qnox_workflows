# qnox/workflows

Laravel workflow package shaped around the approval flow used in `IOO-WEB-V2`: applicant/start level, sequential definition-like levels, per-level track history, assignee resolution, and first-class actions such as `submit`, `approve`, `reject`, `return`, `hold`, `resume`, `recall`, and `complete`.

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
$engine->submit($instance, auth()->user(), ['comment' => 'Submitted to supervisor']);
$engine->approve($instance->fresh(), auth()->user(), ['comment' => 'Approved']);
```

## IOO-WEB-V2 Style Structure
Create workflows as ordered levels instead of only arbitrary graph edges.

- Level 1 should usually represent the applicant or initiator.
- Set assignment criteria `['initiator' => true]` on the first level to let the submitter own that level.
- Later levels can use direct assignees or criteria such as `user_ids`, `designation_id`, `department_id`, or permission checks.
- If you define explicit `workflow_transitions`, they are honored first.
- If you do not define transitions, the engine falls back to sequential level flow based on `sequence`.

## Lifecycle
- `submit`: move start/applicant level to the next level
- `approve`: move forward to the next level
- `reject`: move backward and mark the instance rejected
- `return`: move backward for rework
- `hold`: keep the item on the same level and mark it on hold
- `resume`: continue a held workflow on the same level
- `recall`: move the item back toward the start level
- `complete`: close the workflow
