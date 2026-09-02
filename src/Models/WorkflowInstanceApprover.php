<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceApprover extends Model
{
    protected $fillable = ['workflow_instance_level_id', 'approver_type', 'approver_id', 'status', 'acted_at'];
    protected $casts = ['acted_at' => 'datetime'];
    public function instanceLevel() { return $this->belongsTo(WorkflowInstanceLevel::class); }
    public function approver() { return $this->morphTo(); }
}
