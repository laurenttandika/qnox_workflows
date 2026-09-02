<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['module_key', 'name', 'slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function levels() { return $this->hasMany(WorkflowLevel::class); }
    public function instances() { return $this->hasMany(WorkflowInstance::class); }

    public function startLevel()
    {
        return $this->levels()->orderBy('sequence')->first();
    }
}
