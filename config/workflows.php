<?php

return [
    'modules' => [
        // 'hr.leave' => 'Leave',
        // 'hr.expenses' => 'Expenses',
    ],
    'user_model' => App\Models\User::class,
    'providers' => [
        'users' => Qnox\Workflows\Services\EloquentUserProvider::class,
        'roles' => Qnox\Workflows\Services\UnconfiguredRoleProvider::class,
    ],
    'resolvers' => [
        'supervisor' => Qnox\Workflows\Services\UnconfiguredSupervisorResolver::class,
        'roles' => Qnox\Workflows\Services\UnconfiguredRoleProvider::class,
    ],
    'eligibility' => ['active_attribute' => 'is_active'],
    'users' => ['label_attributes' => ['name', 'email'], 'search_attributes' => ['name', 'email'], 'option_limit' => 100],
    'notify_channels' => ['mail'],
    'routes' => [
        'web' => ['enabled' => true, 'prefix' => 'settings/workflows', 'name_prefix' => 'workflows.', 'middleware' => ['web', 'auth']],
        'inbox' => ['enabled' => true, 'prefix' => 'workflows/inbox', 'middleware' => ['web', 'auth']],
        'api' => ['enabled' => true, 'prefix' => 'api/workflows', 'middleware' => ['api', 'auth:sanctum']],
    ],
    'views' => [
        'modules' => 'workflows::settings.modules', 'definitions' => 'workflows::settings.definitions',
        'definition' => 'workflows::settings.definition', 'instance' => 'workflows::instances.show',
        'inbox' => 'workflows::inbox.index',
    ],
    'permissions' => ['view' => 'workflows.view', 'manage' => 'workflows.manage', 'act' => 'workflows.act'],
];
