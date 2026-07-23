<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowModule extends Model
{
    protected $fillable = [
        'workflow_group_id',
        'moduleable_type',
        'moduleable_id',
        'name',
        'slug',
        'handler',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(WorkflowGroup::class, 'workflow_group_id');
    }

    public function moduleable()
    {
        return $this->morphTo();
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}
