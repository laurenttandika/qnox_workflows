<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    protected $fillable = ['workflow_id','subject_type','subject_id','current_level_id','status','context'];
    protected $casts = ['context' => 'array'];

    public function workflow() { return $this->belongsTo(Workflow::class); }
    public function subject() { return $this->morphTo(); }
    public function currentLevel() { return $this->belongsTo(WorkflowLevel::class, 'current_level_id'); }
    public function history() { return $this->hasMany(WorkflowInstanceLevel::class); }
    public function actions() { return $this->hasMany(WorkflowAction::class); }
}