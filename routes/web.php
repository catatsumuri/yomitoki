<?php

use App\Http\Controllers\ArchivesController;
use App\Http\Controllers\ArticlesController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ScrapController;
use App\Http\Controllers\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    return Inertia::render('welcome', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('scraps/suggest-metadata', [ScrapController::class, 'suggestMetadata'])->name('scraps.suggest-metadata');
    Route::post('scraps/upload-image', [ScrapController::class, 'uploadImage'])->name('scraps.upload-image');
    Route::post('scraps', [ScrapController::class, 'store'])->name('scraps.store');
    Route::patch('scraps/{scrap}', [ScrapController::class, 'update'])->name('scraps.update');
    Route::post('scraps/bulk-archive', [ScrapController::class, 'bulkArchive'])->name('scraps.bulk-archive');
    Route::post('scraps/{scrap}/archive', [ScrapController::class, 'archive'])->name('scraps.archive');
    Route::post('scraps/{scrap}/restore', [ScrapController::class, 'restore'])->name('scraps.restore');
    Route::delete('scraps/{scrap}', [ScrapController::class, 'destroy'])->name('scraps.destroy');
    Route::get('archives', [ArchivesController::class, 'index'])->name('archives');
    Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::get('backup/bulk-stream', [BackupController::class, 'bulkStream'])->name('backup.bulk-stream');
    Route::post('scraps/{scrap}/backup', [BackupController::class, 'backupScrap'])->name('scraps.backup');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/{slug}', [DashboardController::class, 'show'])->name('dashboard.show');
    Route::get('articles', [ArticlesController::class, 'index'])->name('articles');
    Route::get('articles/{slug}', [ArticlesController::class, 'show'])->name('articles.show');
    Route::post('documents/compose', [DocumentController::class, 'compose'])->name('documents.compose');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search/init', [SearchController::class, 'init'])->name('search.init');
    Route::post('search/query', [SearchController::class, 'query'])->name('search.query');
});

Route::get('images/{path}', [ImageController::class, 'show'])
    ->where('path', '.*')
    ->name('images.show');

require __DIR__.'/settings.php';
