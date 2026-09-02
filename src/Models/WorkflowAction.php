<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    protected $fillable = [
        'workflow_instance_id', 'workflow_instance_level_id',
        'actor_type', 'actor_id', 'action', 'comment',
    ];

    public function instance() { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function instanceLevel() { return $this->belongsTo(WorkflowInstanceLevel::class); }
    public function actor() { return $this->morphTo(); }
}
