<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use App\Support\ScrapSlugHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:add {--title= : Scrap title} {--slug= : Slug candidate (auto-uniquified if duplicate)} {--content= : Content as string} {--file= : Path to content file (content or file required)} {--source-type=note : Source type (plan, note, result, research, meeting, execution)} {--parent= : Parent scrap slug} {--description= : Short description saved as summary (skips AI summarization)}')]
#[Description('Add a new scrap to the database')]
class AddScrapCommand extends Command
{
    private const VALID_SOURCE_TYPES = ['plan', 'note', 'result', 'research', 'meeting', 'execution'];

    public function handle(): int
    {
        $title = $this->option('title');

        if (! $title) {
            $this->error('--title is required.');

            return self::FAILURE;
        }

        $content = $this->option('content');
        $file = $this->option('file');

        if ($content && $file) {
            $this->error('--content and --file are mutually exclusive.');

            return self::FAILURE;
        }

        if (! $content && ! $file) {
            $this->error('Either --content or --file is required.');

            return self::FAILURE;
        }

        if ($file) {
            if (! file_exists($file)) {
                $this->error(__('File not found: :file', ['file' => $file]));

                return self::FAILURE;
            }

            $content = file_get_contents($file);
        }

        $sourceType = $this->option('source-type');

        if (! in_array($sourceType, self::VALID_SOURCE_TYPES, true)) {
            $this->error('Invalid --source-type. Must be one of: '.implode(', ', self::VALID_SOURCE_TYPES));

            return self::FAILURE;
        }

        $user = User::first();

        if (! $user) {
            $this->error('No user found.');

            return self::FAILURE;
        }

        $parentId = null;

        if ($parentSlug = $this->option('parent')) {
            $parent = Scrap::where('slug', $parentSlug)->first();

            if (! $parent) {
                $this->error("Parent scrap not found: {$parentSlug}");

                return self::FAILURE;
            }

            $parentId = $parent->id;
        }

        $slug = ScrapSlugHelper::makeUnique($this->option('slug'), $title);
        $description = $this->option('description');

        $scrap = Scrap::create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'source_type' => $sourceType,
            'title' => $title,
            'slug' => $slug,
            'summary' => $description ?: null,
            'content' => $content,
            'content_markdown' => $content,
            'status' => 'processed',
            'occurred_at' => now(),
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);

        if (! $description) {
            SummarizeScrapJob::dispatch($scrap->id);
        }

        $this->info(__('Scrap added: scrap #:id (slug: :slug) ":title"', [
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'title' => $scrap->title,
        ]));

        return self::SUCCESS;
    }
}
