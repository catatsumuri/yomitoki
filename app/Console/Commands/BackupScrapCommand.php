<?php

namespace App\Console\Commands;

use App\Models\Scrap;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

#[Signature('scraps:backup {scrap? : Scrap slug or ID} {--all : Back up all scraps to storage/app/private/backups/} {--output= : Output directory (default: current directory)}')]
#[Description('Create a zip backup of a scrap or all scraps')]
class BackupScrapCommand extends Command
{
    public function handle(): int
    {
        return $this->option('all') ? $this->backupAll() : $this->backupOne();
    }

    private function backupAll(): int
    {
        $user = User::first();

        if (! $user) {
            $this->error('No user found.');

            return self::FAILURE;
        }

        $scraps = Scrap::where('user_id', $user->id)->get();

        if ($scraps->isEmpty()) {
            $this->warn('No scraps found.');

            return self::SUCCESS;
        }

        $filename = 'yomitoki-backup-'.now()->format('Ymd-His').'.zip';
        $tmpPath = tempnam(sys_get_temp_dir(), 'yomitoki-backup-');

        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($scraps as $scrap) {
            $this->addScrapToZip($zip, $scrap, 'scraps/');
        }

        $imagePath = storage_path("app/public/scraps/{$user->id}");

        if (is_dir($imagePath)) {
            $this->addImageDirectoryToZip($zip, $imagePath);
        }

        $zip->close();

        Storage::put("backups/{$filename}", file_get_contents($tmpPath));
        unlink($tmpPath);

        $this->info("Backup saved: storage/app/private/backups/{$filename} ({$scraps->count()} scraps)");

        return self::SUCCESS;
    }

    private function backupOne(): int
    {
        $identifier = $this->argument('scrap');

        if (! $identifier) {
            $this->error('Provide a scrap slug/ID or use --all.');

            return self::FAILURE;
        }

        $scrap = is_numeric($identifier)
            ? Scrap::find((int) $identifier)
            : Scrap::where('slug', $identifier)->first();

        if (! $scrap) {
            $this->error("Scrap not found: {$identifier}");

            return self::FAILURE;
        }

        $outputDir = $this->option('output') ?? getcwd();
        $filename = ($scrap->slug ?? $scrap->id).'-'.now()->format('Ymd-His').'.zip';
        $outputPath = rtrim($outputDir, '/').'/'.$filename;

        $zip = new ZipArchive;
        $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $this->addScrapToZip($zip, $scrap, '');
        $this->addReferencedImages($zip, $scrap);

        foreach ($scrap->children as $child) {
            $this->addScrapToZip($zip, $child, 'children/');
            $this->addReferencedImages($zip, $child);
        }

        $zip->close();

        $this->info("Backup saved: {$outputPath}");

        return self::SUCCESS;
    }

    private function addScrapToZip(ZipArchive $zip, Scrap $scrap, string $prefix): void
    {
        $filename = $scrap->slug ?? $scrap->id;

        $zip->addFromString(
            "{$prefix}{$filename}.md",
            $scrap->content_markdown ?? $scrap->content ?? '',
        );

        $zip->addFromString(
            "{$prefix}{$filename}.meta.json",
            json_encode([
                'id' => $scrap->id,
                'title' => $scrap->title,
                'slug' => $scrap->slug,
                'status' => $scrap->status,
                'source_type' => $scrap->source_type,
                'occurred_at' => $scrap->occurred_at,
                'created_at' => $scrap->created_at,
                'parent_id' => $scrap->parent_id,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function addReferencedImages(ZipArchive $zip, Scrap $scrap): void
    {
        preg_match_all('/!\[.*?\]\((\/images\/[^\s)]+)\)/', $scrap->content_markdown ?? $scrap->content ?? '', $matches);

        foreach ($matches[1] as $imageUrl) {
            $publicRelativePath = ltrim(Str::after($imageUrl, '/images/'), '/');
            $userImagePrefix = "scraps/{$scrap->user_id}/";

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
