<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Support\ScrapSlugHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:update {--slug= : Slug of the scrap to update} {--title= : New title} {--new-slug= : New slug (auto-uniquified if duplicate)} {--content= : New content as string} {--file= : Path to new content file} {--status= : New status (raw, processed, archived)}')]
#[Description('Update an existing scrap by slug')]
class UpdateScrapCommand extends Command
{
    public function handle(): int
    {
        $slug = $this->option('slug');

        if (! $slug) {
            $this->error('--slug is required.');

            return self::FAILURE;
        }

        $scrap = Scrap::where('slug', $slug)->first();

        if (! $scrap) {
            $this->error("Scrap not found: {$slug}");

            return self::FAILURE;
        }

        $content = $this->option('content');
        $file = $this->option('file');

        if ($content && $file) {
            $this->error('--content and --file are mutually exclusive.');

            return self::FAILURE;
        }

        if ($file) {
            if (! file_exists($file)) {
                $this->error(__('File not found: :file', ['file' => $file]));

                return self::FAILURE;
            }

            $content = file_get_contents($file);
        }

        $status = $this->option('status');

        if ($status && ! in_array($status, ['raw', 'processed', 'archived'], true)) {
            $this->error('Invalid --status. Must be one of: raw, processed, archived');

            return self::FAILURE;
        }

        $updateData = [];

        if ($title = $this->option('title')) {
            $updateData['title'] = $title;
        }

        if ($newSlug = $this->option('new-slug')) {
            $updateData['slug'] = ScrapSlugHelper::makeUnique($newSlug, $this->option('title') ?? $scrap->title, $scrap->id);
        }

        if ($content !== null) {
            $updateData['content'] = $content;
            $updateData['content_markdown'] = $content;
        }

        if ($status) {
            $updateData['status'] = $status;
        }

        if ($updateData === []) {
            $this->warn('No fields to update.');

            return self::SUCCESS;
        }

        $scrap->update($updateData);

        if (isset($updateData['content'])) {
            GenerateScrapEmbeddingJob::dispatch($scrap->id);
            SummarizeScrapJob::dispatch($scrap->id);
        }

        $this->info(__('Scrap updated: scrap #:id (slug: :slug) ":title"', [
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'title' => $scrap->title,
        ]));

        return self::SUCCESS;
    }
}
