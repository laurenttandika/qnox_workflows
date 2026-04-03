<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_id',
        'from_level_id',
        'to_level_id',
        'action_key',
        'direction',
        'guard',
        'label',
        'status',
        'meta',
        'form_schema',
    ];

    protected $casts = [
        'guard' => 'array',
        'meta' => 'array',
        'form_schema' => 'array',
    ];

    public function workflow() { return $this->belongsTo(Workflow::class); }
    public function fromLevel() { return $this->belongsTo(WorkflowLevel::class, 'from_level_id'); }
    public function toLevel() { return $this->belongsTo(WorkflowLevel::class, 'to_level_id'); }
}
