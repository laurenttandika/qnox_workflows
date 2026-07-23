<?php

namespace Qnox\Workflows\Support;

class WorkflowMenu
{
    public static function inbox(?object $user = null): array
    {
        $counts = $user ? app(\Qnox\Workflows\Services\WorkflowInbox::class)->counts($user) : [];

        return collect(['new', 'pending', 'attended', 'responded', 'held', 'ended'])
            ->map(fn (string $category) => [
                'label' => ucfirst($category),
                'route' => self::route('inbox.'.$category),
                'route_parameters' => [],
                'badge' => $counts[$category] ?? null,
                'permission' => config('workflows.permissions.view'),
            ])
            ->all();
    }

    public static function items(): array
    {
        return [[
            'label' => 'Workflow Settings',
            'route' => config('workflows.routes.web.name_prefix', 'workflows.').'dashboard',
            'permission' => config('workflows.permissions.manage'),
            'icon' => 'fa fa-project-diagram',
            'children' => [
                ['label' => 'Module Groups', 'route' => self::route('groups.index')],
                ['label' => 'Modules', 'route' => self::route('modules.index')],
                ['label' => 'Definitions', 'route' => self::route('definitions.index')],
                ['label' => 'Workflow Permissions', 'route' => self::route('participants.index')],
                ['label' => 'Number Formats', 'route' => self::route('numbers.index')],
            ],
        ]];
    }

    protected static function route(string $name): string
    {
        return config('workflows.routes.web.name_prefix', 'workflows.').$name;
    }
}
