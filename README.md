# qnox/workflows

Dynamic, transition-driven workflow engine for Laravel. Admins can define any action keyword and route levels accordingly.

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
$engine->act($instance, 'approve', auth()->user(), ['comment' => 'Ok']);
```