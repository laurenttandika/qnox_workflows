<?php

use Illuminate\Support\Facades\Route;
use Qnox\Workflows\Http\Controllers\{InboxController, InstanceController, SettingsController};

Route::prefix(config('workflows.routes.web.prefix', 'settings/workflows'))
    ->middleware(config('workflows.routes.web.middleware', ['web', 'auth']))
    ->name(config('workflows.routes.web.name_prefix', 'workflows.'))
    ->group(function () {
        Route::get('/', [SettingsController::class, 'modules'])->name('modules.index');
        Route::get('/modules/{module}/workflows', [SettingsController::class, 'definitions'])->where('module', '.*')->name('definitions.index');
        Route::get('/modules/{module}/workflows/create', [SettingsController::class, 'create'])->where('module', '.*')->name('definitions.create');
        Route::post('/workflows', [SettingsController::class, 'store'])->name('definitions.store');
        Route::get('/workflows/{workflow}/edit', [SettingsController::class, 'edit'])->name('definitions.edit');
        Route::put('/workflows/{workflow}', [SettingsController::class, 'update'])->name('definitions.update');
        Route::get('/instances/{instance}', [InstanceController::class, 'show'])->name('instances.show');
        Route::post('/instances/{instance}/decide', [InstanceController::class, 'decide'])->name('instances.decide');
    });

if (config('workflows.routes.inbox.enabled', true)) {
    Route::prefix(config('workflows.routes.inbox.prefix', 'workflows/inbox'))
        ->middleware(config('workflows.routes.inbox.middleware', ['web', 'auth']))
        ->name(config('workflows.routes.web.name_prefix', 'workflows.').'inbox.')
        ->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('/counts', [InboxController::class, 'counts'])->name('counts');
            Route::get('/item/{instance}', [InboxController::class, 'show'])->name('show');
        });
}
