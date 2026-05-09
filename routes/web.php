<?php

declare(strict_types=1);

use FahimTayebee\Preflight\Http\Controllers\ReportUiController;
use Illuminate\Support\Facades\Route;

if (config('preflight.ui.enabled') === true) {
    $path = trim((string) config('preflight.ui.path', 'preflight'), '/');
    $middleware = (array) config('preflight.ui.middleware', ['web']);

    Route::middleware($middleware)
        ->prefix($path === '' ? 'preflight' : $path)
        ->group(function (): void {
            Route::get('/', [ReportUiController::class, 'index'])->name('preflight.index');
            Route::get('/latest', [ReportUiController::class, 'latest'])->name('preflight.latest');
            Route::get('/report/{filename}', [ReportUiController::class, 'show'])->name('preflight.report');
        });
}
