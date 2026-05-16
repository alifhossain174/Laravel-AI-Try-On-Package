<?php

use Illuminate\Support\Facades\Route;
use FahimHossain\LaravelAiTryon\Http\Controllers\TryOnController;

Route::prefix(config('ai-tryon.routes.prefix', 'ai-tryon'))
    ->middleware(config('ai-tryon.routes.middleware', ['web', 'throttle:ai-tryon']))
    ->name('ai-tryon.')
    ->group(function (): void {
        Route::post('/generate', [TryOnController::class, 'store'])->name('generate');
        Route::get('/generations/{generation:uuid}', [TryOnController::class, 'show'])->name('generations.show');
    });
