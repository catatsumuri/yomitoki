<?php

use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('config', '/config/api-tokens');

    Route::get('config/api-tokens', [ApiTokenController::class, 'edit'])->name('api-tokens.edit');
    Route::post('config/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('config/api-tokens', [ApiTokenController::class, 'bulkDestroy'])->name('api-tokens.bulk-destroy');
    Route::get('config/api-tokens/{tokenId}', [ApiTokenController::class, 'show'])->name('api-tokens.show');
    Route::post('config/api-tokens/{tokenId}/regenerate', [ApiTokenController::class, 'regenerate'])->name('api-tokens.regenerate');
    Route::delete('config/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::get('config/ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
    Route::get('config/ai/ping', [AiSettingsController::class, 'ping'])->name('ai.ping');
});
