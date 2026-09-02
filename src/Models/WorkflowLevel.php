<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowLevel extends Model
{
    protected $fillable = [
        'workflow_id', 'name', 'sequence', 'approver_type', 'approver_role',
        'rejection_comment_required',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'rejection_comment_required' => 'boolean',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
    public function selectedUsers() { return $this->hasMany(WorkflowLevelUser::class); }
}
