<?php

namespace Qnox\Workflows\Concerns;

use Illuminate\Database\Eloquent\Model;
use Qnox\Workflows\Services\NumberGenerator;

trait HasWorkflowNumber
{
    public function generateWorkflowNumber(
        string $key,
        array $context = [],
        ?Model $scope = null
    ): string {
        return app(NumberGenerator::class)->next($key, array_merge([
            'subject_id' => $this->getKey(),
        ], $context), $scope);
    }
}
