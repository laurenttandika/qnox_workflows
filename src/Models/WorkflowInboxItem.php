<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInboxItem extends Model
{
    public const NEW = 'new';
    public const ATTENDED = 'attended';
    public const RESPONDED = 'responded';
    public const ENDED = 'ended';

    protected $fillable = [
        'workflow_instance_id',
        'workflow_instance_level_id',
        'recipient_type',
        'recipient_id',
        'status',
        'opened_at',
        'responded_at',
        'ended_at',
        'workflow_action_id',
        'meta',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'responded_at' => 'datetime',
        'ended_at' => 'datetime',
        'meta' => 'array',
    ];

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function instanceLevel()
    {
        return $this->belongsTo(WorkflowInstanceLevel::class, 'workflow_instance_level_id');
    }

    public function recipient()
    {
        return $this->morphTo();
    }

    public function action()
    {
        return $this->belongsTo(WorkflowAction::class, 'workflow_action_id');
    }
}
