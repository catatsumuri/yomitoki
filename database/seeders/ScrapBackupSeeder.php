<?php

namespace Database\Seeders;

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Database\Seeder;
use ZipArchive;

class ScrapBackupSeeder extends Seeder
{
    /**
     * Restore scraps from all zip files found under storage/app/private/backups/.
     *
     * Supports both formats:
     *   - Full backup (download / scraps:backup --all): scraps/{slug}.md + scraps/{slug}.meta.json
     *   - Individual backup (scraps:backup {scrap}): {slug}.md + {slug}.meta.json
     *
     * All zips are processed oldest-first so the most recent backup of each slug wins.
     */
    public function run(): void
    {
        $zips = $this->findAllZips();

        if (empty($zips)) {
            $this->command->warn('No zip files found under storage/app/private/backups/.');

            return;
        }

        $user = User::first();

        if (! $user) {
            $this->command->error('No user found. Create a user first.');

            return;
        }

        $total = 0;

        foreach ($zips as $zipPath) {
            $count = $this->restoreFromZip($zipPath, $user->id);
            $this->command->line("  {$count} scrap(s) ← ".basename($zipPath));
            $total += $count;
        }

        $this->command->info("Restored {$total} scrap(s) total from ".count($zips).' zip(s).');
    }

    private function restoreFromZip(string $zipPath, int $userId): int
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $prefix = $this->detectPrefix($zip);
        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (! str_ends_with($name, '.meta.json')) {
                continue;
            }

            if (! str_starts_with($name, $prefix)) {
                continue;
            }

            if (str_contains(substr($name, strlen($prefix)), 'children/')) {
                continue;
            }

            $meta = json_decode($zip->getFromIndex($i), true);

            if (! isset($meta['slug'])) {
                continue;
            }

            $mdName = substr($name, 0, -strlen('.meta.json')).'.md';
            $markdown = $zip->getFromName($mdName) ?: '';

            Scrap::updateOrCreate(
                ['user_id' => $userId, 'slug' => $meta['slug']],
                [
                    'title' => $meta['title'] ?? null,
                    'status' => $meta['status'] ?? 'unprocessed',
                    'source_type' => $meta['source_type'] ?? 'note',
                    'occurred_at' => $meta['occurred_at'] ?? now(),
                    'content' => $markdown,
                    'content_markdown' => $markdown,
                ],
            );

            $count++;
        }

        $zip->close();

        return $count;
    }

    /** @return string[] oldest-first */
    private function findAllZips(): array
    {
        $candidates = array_merge(
            glob(storage_path('app/private/backups/*.zip')) ?: [],
            glob(storage_path('app/private/backups/*/*.zip')) ?: [],
        );

        usort($candidates, fn ($a, $b) => filemtime($a) - filemtime($b));

        return $candidates;
    }

    private function detectPrefix(ZipArchive $zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'scraps/')) {
                return 'scraps/';
            }
        }

        return '';
    }
}
