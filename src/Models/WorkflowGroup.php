<?php

namespace Qnox\Workflows\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowGroup extends Model
{
    protected $fillable = ['name','slug'];

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}