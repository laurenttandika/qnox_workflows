<?php

use Illuminate\Support\Facades\Route;
use Qnox\Workflows\Http\Controllers\InstanceController;

Route::middleware(['api','auth:sanctum'])->group(function () {
    Route::get('/workflow-instances/{instance}/actions', [InstanceController::class, 'actions']);
    Route::post('/workflow-instances/{instance}/act', [InstanceController::class, 'act']);
});