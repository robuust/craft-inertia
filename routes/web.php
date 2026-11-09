<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Robuust\CraftInertiaPoc\Http\EntriesController;
use Robuust\CraftInertiaPoc\Http\HandleInertiaRequests;

Route::prefix('inertia-poc')
    ->name('inertia-poc.')
    ->middleware(HandleInertiaRequests::class)
    ->group(function (): void {
        Route::get('/', [EntriesController::class, 'index'])->name('index');
        Route::get('entries/{entryId}', [EntriesController::class, 'show'])
            ->whereNumber('entryId')
            ->name('entries.show');
    });
