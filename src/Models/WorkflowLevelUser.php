<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowLevelUser extends Model
{
    protected $fillable = ['workflow_level_id', 'user_id'];
    public function level() { return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id'); }
}
