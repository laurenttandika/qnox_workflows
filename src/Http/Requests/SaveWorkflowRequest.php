<?php

namespace Qnox\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Qnox\Workflows\Contracts\{ModuleRegistry, RoleProvider, UserProvider};

class SaveWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = config('workflows.permissions.manage');
        return $this->user() && (!$permission || $this->user()->can($permission));
    }

    public function rules(): array
    {
        return [
            'module_key' => ['required', 'string', Rule::in(app(ModuleRegistry::class)->all()->keys()->all())],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('workflows', 'slug')
                ->where(fn ($query) => $query->where('module_key', $this->input('module_key')))
                ->ignore($this->route('workflow'))],
            'is_active' => ['sometimes', 'boolean'],
            'confirm_reorder' => ['sometimes', 'boolean'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*.id' => ['nullable', 'integer'],
            'levels.*.name' => ['required', 'string', 'max:120'],
            'levels.*.approver_type' => ['required', Rule::in(['supervisor', 'role', 'users'])],
            'levels.*.approver_role' => ['nullable', 'string', 'max:191'],
            'levels.*.user_ids' => ['nullable', 'array'],
            'levels.*.user_ids.*' => ['required', function ($attribute, $value, $fail) {
                if (!is_string($value) && !is_int($value)) $fail('Each selected employee must have a scalar identifier.');
                elseif (strlen((string) $value) > 191) $fail('An employee identifier is too long.');
            }],
            'levels.*.rejection_comment_required' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $validRoles = app(RoleProvider::class)->options()->pluck('value')->map(fn ($v) => (string) $v);
            foreach ($this->input('levels', []) as $index => $level) {
                $type = $level['approver_type'] ?? null;
                $role = $level['approver_role'] ?? null;
                $ids = array_values(array_unique(array_filter($level['user_ids'] ?? [], fn ($id) => $id !== '')));
                if ($type === 'role' && (!$role || !$validRoles->contains((string) $role))) $validator->errors()->add("levels.{$index}.approver_role", 'Select one valid role.');
                if ($type !== 'role' && $role) $validator->errors()->add("levels.{$index}.approver_role", 'A role is only valid for role approvers.');
                if ($type === 'users' && !$ids) $validator->errors()->add("levels.{$index}.user_ids", 'Select at least one employee.');
                if ($type !== 'users' && $ids) $validator->errors()->add("levels.{$index}.user_ids", 'Employees are only valid for selected-employee approvers.');
                if ($type === 'users' && app(UserProvider::class)->findMany($ids)->count() !== count($ids)) $validator->errors()->add("levels.{$index}.user_ids", 'One or more selected employees no longer exist.');
            }
        }];
    }
}
