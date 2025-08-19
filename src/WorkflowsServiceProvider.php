<?php

namespace Qnox\Workflows;

use Illuminate\Support\ServiceProvider;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Services\DefaultAssignmentResolver;
use Qnox\Workflows\Services\Guards\GuardEvaluator;

class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');

        $this->app->bind(AssignmentResolver::class, function ($app) {
            $class = config('workflows.assignment_resolver', DefaultAssignmentResolver::class);
            return $app->make($class);
        });

        $this->app->singleton(GuardEvaluator::class, fn() => new GuardEvaluator());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'qnox-workflows-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'qnox-workflows-migrations');
    }
}
