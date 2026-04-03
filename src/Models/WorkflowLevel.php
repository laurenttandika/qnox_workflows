<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowLevel extends Model
{
    protected $fillable = [
        'workflow_id',
        'name',
        'sequence',
        'is_start',
        'is_terminal',
        'rules',
        'description',
        'msg_next',
        'allow_rejection',
        'allow_repeat_participate',
        'allow_round_robin',
        'has_next_start_optional',
        'is_optional',
        'is_approval',
        'can_close',
        'action_description',
        'status_description',
    ];

    protected $casts = [
        'is_start' => 'boolean',
        'is_terminal' => 'boolean',
        'rules' => 'array',
        'allow_rejection' => 'boolean',
        'allow_repeat_participate' => 'boolean',
        'allow_round_robin' => 'boolean',
        'has_next_start_optional' => 'boolean',
        'is_optional' => 'boolean',
        'is_approval' => 'boolean',
        'can_close' => 'boolean',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
    public function assignments()
    {
        return $this->hasMany(WorkflowAssignment::class);
    }
    public function outgoingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, 'from_level_id');
    }
    public function incomingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, 'to_level_id');
    }
}
