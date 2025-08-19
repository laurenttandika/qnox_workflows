<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['workflow_group_id','name','slug','is_active','meta'];
    protected $casts = ['meta' => 'array', 'is_active' => 'boolean'];

    public function group() { return $this->belongsTo(WorkflowGroup::class, 'workflow_group_id'); }
    public function levels() { return $this->hasMany(WorkflowLevel::class); }
    public function transitions() { return $this->hasMany(WorkflowTransition::class); }

    public function startLevel()
    {
        return $this->levels()->where('is_start', true)->orderBy('sequence')->first();
    }
}