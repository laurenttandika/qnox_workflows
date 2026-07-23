<?php

return [
    'user_model' => App\Models\User::class,
    'assignment_resolver' => Qnox\Workflows\Services\DefaultAssignmentResolver::class,
    'assignment_providers' => [
        'user' => Qnox\Workflows\Assignments\UserAssignmentProvider::class,
    ],
    'notify_channels' => ['mail'],

    'routes' => [
        'web' => [
            'enabled' => true,
            'prefix' => 'settings/workflows',
            'name_prefix' => 'workflows.',
            'middleware' => ['web', 'auth'],
        ],
        'api' => [
            'enabled' => true,
            'legacy_routes' => true,
            'prefix' => 'api/workflows',
            'middleware' => ['api', 'auth:sanctum'],
        ],
    ],

    'views' => [
        'layout' => null,
        'dashboard' => 'workflows::settings.dashboard',
        'groups' => 'workflows::settings.groups',
        'modules' => 'workflows::settings.modules',
        'definitions' => 'workflows::settings.definitions',
        'definition' => 'workflows::settings.definition',
        'numbers' => 'workflows::settings.numbers',
        'instance' => 'workflows::instances.show',
        'actions' => 'workflows::actions.buttons',
        'action_modal' => 'workflows::actions.modal',
    ],

    'permissions' => [
        'view' => 'workflows.view',
        'manage' => 'workflows.manage',
        'act' => 'workflows.act',
    ],

    'numbering' => [
        'default_padding' => 6,
        'allowed_tokens' => [
            'prefix', 'number', 'year', 'month', 'day', 'module',
            'department', 'unit', 'tenant', 'subject_id',
        ],
    ],

    'action_labels' => [
        'submit' => 'Submit',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'return' => 'Return',
        'hold' => 'Hold',
        'resume' => 'Resume',
        'recall' => 'Recall',
        'complete' => 'Complete',
    ],
];
