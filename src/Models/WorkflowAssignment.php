<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAssignment extends Model
{
    protected $fillable = ['workflow_level_id','assignable_type','assignable_id','type','criteria'];
    protected $casts = ['criteria' => 'array'];

    public function level() { return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id'); }
    public function assignable() { return $this->morphTo(); }
}