<?php

namespace Qnox\Workflows\Support;

class WorkflowMenu
{
    public static function inbox(?object $user = null): array
    {
        $counts = $user ? app(\Qnox\Workflows\Services\WorkflowInbox::class)->counts($user) : [];

        return collect(['pending', 'responded', 'ended'])
            ->map(fn (string $category) => [
                'label' => ucfirst($category),
                'route' => self::route('inbox.index'),
                'route_parameters' => ['category' => $category],
                'badge' => $counts[$category] ?? null,
                'permission' => config('workflows.permissions.view'),
            ])
            ->all();
    }

    public static function items(): array
    {
        return [[
            'label' => 'Workflow Settings',
            'route' => self::route('modules.index'),
            'permission' => config('workflows.permissions.manage'),
            'icon' => 'fa fa-project-diagram',
            'children' => [['label' => 'Registered Modules', 'route' => self::route('modules.index')]],
        ]];
    }

    protected static function route(string $name): string
    {
        return config('workflows.routes.web.name_prefix', 'workflows.').$name;
    }
}
