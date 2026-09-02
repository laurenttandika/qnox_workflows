<?php

namespace Qnox\Workflows;

use Illuminate\Support\ServiceProvider;
use Qnox\Workflows\Contracts\{ModuleRegistry, RoleAssigneeResolver, RoleProvider, SupervisorResolver, UserProvider};
use Qnox\Workflows\Services\{ConfigModuleRegistry, EloquentUserProvider, UnconfiguredRoleProvider, UnconfiguredSupervisorResolver};

class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');

        $this->app->singleton(ModuleRegistry::class, ConfigModuleRegistry::class);
        $this->app->bind(UserProvider::class, fn ($app) => $app->make(config('workflows.providers.users', EloquentUserProvider::class)));
        $this->app->bind(SupervisorResolver::class, fn ($app) => $app->make(config('workflows.resolvers.supervisor', UnconfiguredSupervisorResolver::class)));
        $this->app->bind(RoleProvider::class, fn ($app) => $app->make(config('workflows.providers.roles', UnconfiguredRoleProvider::class)));
        $this->app->bind(RoleAssigneeResolver::class, fn ($app) => $app->make(config('workflows.resolvers.roles', UnconfiguredRoleProvider::class)));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'workflows');

        if (config('workflows.routes.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }

        if (config('workflows.routes.web.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        }

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'qnox-workflows-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'qnox-workflows-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/workflows'),
        ], 'qnox-workflows-views');
    }
}
