<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowLevel extends Model
{
    protected $fillable = ['workflow_id', 'name', 'sequence', 'is_start', 'is_terminal', 'rules'];
    protected $casts = ['is_start' => 'boolean', 'is_terminal' => 'boolean', 'rules' => 'array'];

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
