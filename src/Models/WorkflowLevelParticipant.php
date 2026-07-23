<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowLevelParticipant extends Model
{
    protected $fillable = [
        'workflow_level_id',
        'type',
        'participant_type',
        'participant_id',
        'role',
        'can_view',
        'can_claim',
        'can_act',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_claim' => 'boolean',
        'can_act' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function level()
    {
        return $this->belongsTo(WorkflowLevel::class, 'workflow_level_id');
    }

    public function participant()
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
