<?php

use Illuminate\Support\Facades\Route;
use Qnox\Workflows\Http\Controllers\InstanceController;

Route::prefix(config('workflows.routes.api.prefix', 'api/workflows'))
    ->middleware(config('workflows.routes.api.middleware', ['api', 'auth:sanctum']))
    ->name('api.workflows.')
    ->group(function () {
        Route::get('/instances/{instance}/actions', [InstanceController::class, 'actions'])->name('instances.actions');
        Route::post('/instances/{instance}/decide', [InstanceController::class, 'decide'])->name('instances.decide');
    });
