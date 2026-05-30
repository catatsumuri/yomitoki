<?php

use App\Http\Controllers\Api\IngestScrapController;
use App\Http\Controllers\Api\ScrapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('scraps', IngestScrapController::class)->name('api.scraps.store');
    Route::get('scraps', [ScrapController::class, 'index'])->name('api.scraps.index');
    Route::get('scraps/{slug}', [ScrapController::class, 'show'])->name('api.scraps.show')->where('slug', '[a-z0-9-]+');
    Route::patch('scraps/{slug}', [ScrapController::class, 'update'])->name('api.scraps.update')->where('slug', '[a-z0-9-]+');
    Route::delete('scraps/{slug}', [ScrapController::class, 'destroy'])->name('api.scraps.destroy')->where('slug', '[a-z0-9-]+');
});
