<?php

use Illuminate\Support\Facades\Route;
use Qnox\Workflows\Http\Controllers\InstanceController;
use Qnox\Workflows\Http\Controllers\SettingsController;
use Qnox\Workflows\Http\Controllers\InboxController;

Route::prefix(config('workflows.routes.web.prefix', 'settings/workflows'))
    ->middleware(config('workflows.routes.web.middleware', ['web', 'auth']))
    ->name(config('workflows.routes.web.name_prefix', 'workflows.'))
    ->group(function () {
        Route::get('/', [SettingsController::class, 'dashboard'])->name('dashboard');

        Route::get('/groups', [SettingsController::class, 'groups'])->name('groups.index');
        Route::post('/groups', [SettingsController::class, 'storeGroup'])->name('groups.store');
        Route::put('/groups/{group}', [SettingsController::class, 'updateGroup'])->name('groups.update');

        Route::get('/modules', [SettingsController::class, 'modules'])->name('modules.index');
        Route::post('/modules', [SettingsController::class, 'storeModule'])->name('modules.store');
        Route::put('/modules/{module}', [SettingsController::class, 'updateModule'])->name('modules.update');

        Route::get('/definitions', [SettingsController::class, 'definitions'])->name('definitions.index');
        Route::post('/definitions', [SettingsController::class, 'storeDefinition'])->name('definitions.store');
        Route::get('/definitions/{workflow}', [SettingsController::class, 'definition'])->name('definitions.show');
        Route::post('/definitions/{workflow}/levels', [SettingsController::class, 'storeLevel'])->name('definitions.levels.store');
        Route::post('/definitions/{workflow}/assignments', [SettingsController::class, 'storeAssignment'])->name('definitions.assignments.store');
        Route::post('/definitions/{workflow}/transitions', [SettingsController::class, 'storeTransition'])->name('definitions.transitions.store');

        Route::get('/numbers', [SettingsController::class, 'numbers'])->name('numbers.index');
        Route::post('/numbers', [SettingsController::class, 'storeNumber'])->name('numbers.store');
        Route::put('/numbers/{sequence}', [SettingsController::class, 'updateNumber'])->name('numbers.update');
        Route::get('/numbers/{sequence}/preview', [SettingsController::class, 'previewNumber'])->name('numbers.preview');

        Route::get('/instances/{instance}', [InstanceController::class, 'show'])->name('instances.show');
        Route::post('/instances/{instance}/act', [InstanceController::class, 'act'])->name('instances.act');
    });

if (config('workflows.routes.inbox.enabled', true)) {
    Route::prefix(config('workflows.routes.inbox.prefix', 'workflows/inbox'))
        ->middleware(config('workflows.routes.inbox.middleware', ['web', 'auth']))
        ->name(config('workflows.routes.web.name_prefix', 'workflows.').'inbox.')
        ->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('/counts', [InboxController::class, 'counts'])->name('counts');
            Route::get('/item/{instance}', [InboxController::class, 'show'])->name('show');
            foreach (['new', 'pending', 'attended', 'responded', 'held', 'ended'] as $category) {
                Route::get("/{$category}", [InboxController::class, 'index'])
                    ->defaults('category', $category)
                    ->name($category);
            }
            Route::get('/{category}', [InboxController::class, 'index'])->name('category');
        });
}
