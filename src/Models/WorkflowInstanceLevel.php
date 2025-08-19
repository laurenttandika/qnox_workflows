<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceLevel extends Model
{
    protected $fillable = ['workflow_instance_id','workflow_level_id','entered_at','exited_at','meta'];
    protected $casts = ['entered_at' => 'datetime', 'exited_at' => 'datetime', 'meta' => 'array'];

    public function instance() { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function level() { return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id'); }
}
