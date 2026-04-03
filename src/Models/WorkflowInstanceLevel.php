<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceLevel extends Model
{
    protected $fillable = [
        'workflow_instance_id',
        'workflow_level_id',
        'parent_id',
        'assigned_to_type',
        'assigned_to_id',
        'status',
        'action_key',
        'comments',
        'entered_at',
        'exited_at',
        'receive_date',
        'forward_date',
        'meta',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'receive_date' => 'datetime',
        'forward_date' => 'datetime',
        'meta' => 'array',
    ];

    public function instance() { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function level() { return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id'); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function assignee() { return $this->morphTo('assigned_to'); }
}
