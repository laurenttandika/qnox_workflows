<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceLevel extends Model
{
    protected $fillable = [
        'workflow_instance_id',
        'workflow_level_id',
        'level_name',
        'level_sequence',
        'approver_type',
        'rejection_comment_required',
        'status',
        'entered_at',
        'actioned_at',
        'exited_at',
    ];

    protected $casts = [
        'level_sequence' => 'integer',
        'rejection_comment_required' => 'boolean',
        'entered_at' => 'datetime',
        'actioned_at' => 'datetime',
        'exited_at' => 'datetime',
    ];

    public function instance() { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function level() { return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id'); }
    public function inboxItems() { return $this->hasMany(WorkflowInboxItem::class); }
    public function approvers() { return $this->hasMany(WorkflowInstanceApprover::class); }
}
