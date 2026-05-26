<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use FilesystemIterator;
use Generator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupController extends Controller
{
    public function backupScrap(Request $request, Scrap $scrap): RedirectResponse
    {
        abort_if($scrap->user_id !== $request->user()->id, 403);

        $description = $request->string('description')->trim()->value();

        $scrap->loadMissing('children');
        $this->createAndStoreBackupZip($scrap, $request->user()->id, $description);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Backup saved.'),
        ]);

        return back();
    }

    /**
     * Stream bulk backup progress as Server-Sent Events.
     *
     * GET /backup/bulk-stream?ids[]=1&ids[]=2
     */
    public function bulkStream(Request $request): StreamedResponse
    {
        $ids = array_values(array_filter(array_map('intval', $request->array('ids'))));
        $user = $request->user();

        $scraps = Scrap::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return response()->eventStream(function () use ($scraps, $user): Generator {
            $succeeded = 0;
            $failed = 0;

            foreach ($scraps as $scrap) {
                try {
                    $filename = $this->createAndStoreBackupZip($scrap, $user->id, '');
                    $succeeded++;

                    yield new StreamedEvent(
                        event: 'progress',
                        data: json_encode([
                            'scrapId' => $scrap->id,
                            'title' => $scrap->title ?? $scrap->slug,
                            'status' => 'done',
                            'filename' => $filename,
                        ], JSON_UNESCAPED_UNICODE),
                    );
                } catch (\Throwable) {
                    $failed++;

                    yield new StreamedEvent(
                        event: 'progress',
                        data: json_encode([
                            'scrapId' => $scrap->id,
                            'title' => $scrap->title ?? $scrap->slug,
                            'status' => 'failed',
                        ], JSON_UNESCAPED_UNICODE),
                    );
                }
            }

            yield new StreamedEvent(
                event: 'complete',
                data: json_encode([
                    'total' => $scraps->count(),
                    'succeeded' => $succeeded,
                    'failed' => $failed,
                ]),
            );
        });
    }

    public function download(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        $scraps = Scrap::query()
            ->where('user_id', $user->id)
            ->get();

        $zipPath = tempnam(sys_get_temp_dir(), 'yomitoki-backup-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($scraps as $scrap) {
            $this->addScrapToZip($zip, $scrap, 'scraps/');
        }

        $imagePath = storage_path("app/public/scraps/{$user->id}");

        if (is_dir($imagePath)) {
            $this->addImageDirectoryToZip($zip, $imagePath);
        }

        $zip->close();

        return response()
            ->download($zipPath, 'yomitoki-backup.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Build, store, and return the zip filename for a single scrap backup.
     */
    private function createAndStoreBackupZip(Scrap $scrap, int $userId, string $description): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'yomitoki-scrap-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->addScrapToZip($zip, $scrap, '');
        $this->addReferencedImages($zip, $scrap, $userId);

        foreach ($scrap->children as $child) {
            $this->addScrapToZip($zip, $child, 'children/');
            $this->addReferencedImages($zip, $child, $userId);
        }

        if ($description !== '') {
            $info = implode("\n", array_filter([
                'Scrap: '.($scrap->title ?? $scrap->slug ?? $scrap->id),
                'Created: '.now()->toIso8601String(),
                '',
                $description,
            ]));
            $zip->addFromString('info.txt', $info);
            $zip->setArchiveComment($description);
        }

        $zip->close();

        $filename = ($scrap->slug ?? $scrap->id).'-'.now()->format('Ymd-His').'.zip';
        $storagePath = "backups/{$userId}/{$filename}";

        Storage::put($storagePath, file_get_contents($zipPath));
        unlink($zipPath);

        $meta = [
            'createdAt' => now()->toIso8601String(),
            'description' => $description !== '' ? $description : null,
            'scrapSlug' => $scrap->slug ?? (string) $scrap->id,
        ];
        Storage::put(
            "backups/{$userId}/{$filename}.meta.json",
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        return $filename;
    }

    private function addScrapToZip(ZipArchive $zip, Scrap $scrap, string $prefix): void
    {
        $filename = $scrap->slug ?? $scrap->id;

        $zip->addFromString(
            "{$prefix}{$filename}.md",
            $scrap->content_markdown ?? $scrap->content ?? '',
        );

        $meta = [
            'id' => $scrap->id,
            'title' => $scrap->title,
            'slug' => $scrap->slug,
            'status' => $scrap->status,
            'source_type' => $scrap->source_type,
            'occurred_at' => $scrap->occurred_at,
            'created_at' => $scrap->created_at,
            'parent_id' => $scrap->parent_id,
        ];

        $zip->addFromString(
            "{$prefix}{$filename}.meta.json",
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function addReferencedImages(ZipArchive $zip, Scrap $scrap, int $userId): void
    {
        preg_match_all(
            '/!\[.*?\]\((\/images\/[^\s)]+)\)/',
            $scrap->content_markdown ?? $scrap->content ?? '',
            $matches,
        );

        foreach ($matches[1] as $imageUrl) {
            $publicRelativePath = ltrim(Str::after($imageUrl, '/images/'), '/');
            $userImagePrefix = "scraps/{$userId}/";

            if (! str_starts_with($publicRelativePath, $userImagePrefix)) {
                continue;
            }

            $relativePath = Str::after($publicRelativePath, $userImagePrefix);
            $absolutePath = storage_path("app/public/{$publicRelativePath}");

            if (file_exists($absolutePath)) {
                $zip->addFile($absolutePath, "images/{$relativePath}");
            }
        }
    }

    private function addImageDirectoryToZip(ZipArchive $zip, string $imagePath): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($imagePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', Str::after($file->getPathname(), "{$imagePath}/"));
            $zip->addFile($file->getPathname(), "images/{$relativePath}");
        }
    }
}
