<?php

use Illuminate\Support\Facades\Route;
use Qnox\Workflows\Http\Controllers\InstanceController;

Route::prefix(config('workflows.routes.api.prefix', 'api/workflows'))
    ->middleware(config('workflows.routes.api.middleware', ['api', 'auth:sanctum']))
    ->name(config('workflows.routes.api.name_prefix', 'api.workflows.'))
    ->group(function () {
    Route::get('/instances/{instance}/actions', [InstanceController::class, 'actions'])->name('instances.actions');
    Route::post('/instances/{instance}/act', [InstanceController::class, 'act'])->name('instances.act');
});

if (config('workflows.routes.api.legacy_routes', true)) {
    Route::middleware(config('workflows.routes.api.middleware', ['api', 'auth:sanctum']))
        ->group(function () {
            Route::get('/api/workflow-instances/{instance}/actions', [InstanceController::class, 'actions']);
            Route::post('/api/workflow-instances/{instance}/act', [InstanceController::class, 'act']);
        });
}
