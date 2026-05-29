<?php

use App\Http\Controllers\Api\IngestScrapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->post('scraps', IngestScrapController::class)->name('api.scraps.store');
