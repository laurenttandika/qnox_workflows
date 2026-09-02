<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\UserProvider;

class EloquentUserProvider implements UserProvider
{
    public function options(?string $search = null): Collection
    {
        $model = config('workflows.user_model');
        $query = $model::query();
        $columns = config('workflows.users.search_attributes', ['name', 'email']);
        if ($search !== null && $search !== '' && $columns) $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $index => $column) $index ? $q->orWhere($column, 'like', "%{$search}%") : $q->where($column, 'like', "%{$search}%");
        });
        return $query->limit(config('workflows.users.option_limit', 100))->get()->map(fn ($user) => [
            'value' => $user->getAuthIdentifier(),
            'label' => collect(config('workflows.users.label_attributes', ['name', 'email']))->map(fn ($field) => $user->{$field} ?? null)->first() ?? (string) $user->getAuthIdentifier(),
        ]);
    }

    public function findMany(array $ids): Collection
    {
        $model = config('workflows.user_model');
        return $model::query()->whereKey(array_values(array_unique($ids)))->get();
    }

    public function isEligible(Authenticatable $user): bool
    {
        $attribute = config('workflows.eligibility.active_attribute');
        return !$attribute || !isset($user->{$attribute}) || (bool) $user->{$attribute};
    }
}
