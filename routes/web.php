<?php

use App\Http\Controllers\ArchivesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ScrapController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('scraps/suggest-metadata', [ScrapController::class, 'suggestMetadata'])->name('scraps.suggest-metadata');
    Route::post('scraps/upload-image', [ScrapController::class, 'uploadImage'])->name('scraps.upload-image');
    Route::post('scraps', [ScrapController::class, 'store'])->name('scraps.store');
    Route::patch('scraps/{scrap}', [ScrapController::class, 'update'])->name('scraps.update');
    Route::post('scraps/{scrap}/archive', [ScrapController::class, 'archive'])->name('scraps.archive');
    Route::post('scraps/{scrap}/restore', [ScrapController::class, 'restore'])->name('scraps.restore');
    Route::delete('scraps/{scrap}', [ScrapController::class, 'destroy'])->name('scraps.destroy');
    Route::get('archives', [ArchivesController::class, 'index'])->name('archives');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/{slug}', [DashboardController::class, 'show'])->name('dashboard.show');
});

Route::get('images/{path}', [ImageController::class, 'show'])
    ->where('path', '.*')
    ->name('images.show');

require __DIR__.'/settings.php';
