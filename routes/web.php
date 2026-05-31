<?php

use App\Http\Controllers\ArchivesController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ScrapController;
use App\Http\Controllers\ScrapPageController;
use App\Http\Controllers\ScrapRevisionsController;
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
    Route::delete('scraps/bulk-destroy', [ScrapController::class, 'bulkDestroy'])->name('scraps.bulk-destroy');
    Route::post('scraps/{scrap}/refine-markdown', [ScrapController::class, 'refineMarkdown'])->name('scraps.refine-markdown');
    Route::post('scraps/{scrap}/archive', [ScrapController::class, 'archive'])->name('scraps.archive');
    Route::post('scraps/{scrap}/restore', [ScrapController::class, 'restore'])->name('scraps.restore');
    Route::delete('scraps/{scrap}', [ScrapController::class, 'destroy'])->name('scraps.destroy');
    Route::get('archives', [ArchivesController::class, 'index'])->name('archives');
    Route::get('backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::get('backup/bulk-stream', [BackupController::class, 'bulkStream'])->name('backup.bulk-stream');
    Route::post('scraps/{scrap}/backup', [BackupController::class, 'backupScrap'])->name('scraps.backup');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/{slug}', [DashboardController::class, 'show'])->name('dashboard.show');
    Route::get('dashboard/{slug}/revisions', [ScrapRevisionsController::class, 'show'])->name('dashboard.revisions');
    Route::get('scraps', [ScrapPageController::class, 'index'])->name('scraps');
    Route::get('scraps/{slug}', [ScrapPageController::class, 'show'])->name('scraps.show');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::get('documents/{document}/revisions', [DocumentController::class, 'revisions'])->name('documents.revisions');
    Route::get('documents/{document}/pdf', [DocumentController::class, 'pdf'])->name('documents.pdf');
    Route::patch('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('documents/compose', [DocumentController::class, 'compose'])->name('documents.compose');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search/init', [SearchController::class, 'init'])->name('search.init');
    Route::post('search/query', [SearchController::class, 'query'])->name('search.query');
});

Route::get('images/{path}', [ImageController::class, 'show'])
    ->where('path', '.*')
    ->name('images.show');

require __DIR__.'/settings.php';
