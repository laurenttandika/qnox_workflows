<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_id',
        'subject_type',
        'subject_id',
        'initiator_type',
        'initiator_id',
        'current_level_id',
        'status',
        'context',
        'submitted_at',
        'completed_at',
        'last_action_at',
    ];
    protected $casts = [
        'context' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_action_at' => 'datetime',
    ];

    public function workflow() { return $this->belongsTo(Workflow::class); }
    public function subject() { return $this->morphTo(); }
    public function currentLevel() { return $this->belongsTo(WorkflowLevel::class, 'current_level_id'); }
    public function history() { return $this->hasMany(WorkflowInstanceLevel::class); }
    public function actions() { return $this->hasMany(WorkflowAction::class); }
    public function initiator() { return $this->morphTo(); }
}
