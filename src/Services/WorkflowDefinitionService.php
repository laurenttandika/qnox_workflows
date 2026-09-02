<?php

namespace Qnox\Workflows\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Qnox\Workflows\Models\Workflow;

class WorkflowDefinitionService
{
    public function __construct(protected DatabaseManager $db) {}

    public function save(array $data, ?Workflow $workflow = null): Workflow
    {
        return $this->db->transaction(function () use ($data, $workflow) {
            $workflow ??= new Workflow();
            if ($workflow->exists && $workflow->instances()->where('status', 'in_progress')->exists()) {
                $old = $workflow->levels()->orderBy('sequence')->pluck('id')->map(fn ($id) => (int) $id)->all();
                $new = collect($data['levels'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
                if ($old !== $new && empty($data['confirm_reorder'])) {
                    throw ValidationException::withMessages(['levels' => 'This workflow has active instances. Confirm the level reorder or removal to continue.']);
                }
            }
            $workflow->fill([
                'module_key' => $data['module_key'], 'name' => $data['name'],
                'slug' => $data['slug'] ?: ($workflow->exists ? $workflow->slug : Str::slug($data['name'])), 'is_active' => (bool) ($data['is_active'] ?? false),
            ])->save();
            $workflow->levels()->update(['sequence' => $this->db->raw('sequence + 100000')]);
            $kept = [];
            foreach (array_values($data['levels']) as $index => $input) {
                $level = isset($input['id']) ? $workflow->levels()->findOrFail($input['id']) : $workflow->levels()->make();
                $level->fill([
                    'name' => $input['name'], 'sequence' => $index + 1, 'approver_type' => $input['approver_type'],
                    'approver_role' => $input['approver_type'] === 'role' ? $input['approver_role'] : null,
                    'rejection_comment_required' => (bool) ($input['rejection_comment_required'] ?? false),
                ])->save();
                $kept[] = $level->id;
                $level->selectedUsers()->delete();
                if ($level->approver_type === 'users') foreach (array_unique($input['user_ids'] ?? []) as $id) $level->selectedUsers()->create(['user_id' => $id]);
            }
            $workflow->levels()->whereNotIn('id', $kept)->delete();
            return $workflow->fresh(['levels.selectedUsers']);
        });
    }
}
