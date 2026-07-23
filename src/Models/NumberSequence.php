<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $table = 'workflow_number_sequences';

    protected $fillable = [
        'key',
        'name',
        'format',
        'prefix',
        'next_value',
        'padding',
        'reset_period',
        'reset_marker',
        'scope_type',
        'scope_id',
        'last_generated_at',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'next_value' => 'integer',
        'padding' => 'integer',
        'last_generated_at' => 'datetime',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function scope()
    {
        return $this->morphTo();
    }
}
