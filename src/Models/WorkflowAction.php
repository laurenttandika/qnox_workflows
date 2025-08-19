<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    protected $fillable = [
        'workflow_instance_id','from_level_id','to_level_id',
        'actor_type','actor_id','action_key','comment','payload'
    ];
    protected $casts = ['payload' => 'array'];

    public function instance() { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function fromLevel() { return $this->belongsTo(WorkflowLevel::class, 'from_level_id'); }
    public function toLevel() { return $this->belongsTo(WorkflowLevel::class, 'to_level_id'); }
    public function actor() { return $this->morphTo(); }
}