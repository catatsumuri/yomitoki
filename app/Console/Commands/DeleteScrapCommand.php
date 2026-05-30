<?php

namespace App\Console\Commands;

use App\Models\Scrap;
use App\Support\ScrapSlugHelper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:delete {--slug= : Slug of the scrap to delete} {--force : Skip confirmation prompt}')]
#[Description('Delete a scrap and its children by slug')]
class DeleteScrapCommand extends Command
{
    public function handle(): int
    {
        $slug = $this->option('slug');

        if (! $slug) {
            $this->error('--slug is required.');

            return self::FAILURE;
        }

        $scrap = Scrap::where('slug', $slug)->withCount('children')->first();

        if (! $scrap) {
            $this->error("Scrap not found: {$slug}");

            return self::FAILURE;
        }

        if ($scrap->children_count > 0) {
            $this->warn("This scrap has {$scrap->children_count} child scrap(s) that will also be deleted.");
        }

        if (! $this->option('force') && ! $this->confirm("Delete scrap \"{$slug}\"? This cannot be undone.")) {
            $this->info('Deletion cancelled.');

            return self::SUCCESS;
        }

        $ids = ScrapSlugHelper::collectDescendantIds($scrap);

        Scrap::whereIn('id', $ids)->delete();

        $this->info("Scrap deleted: \"{$slug}\" (".count($ids).' total).');

        return self::SUCCESS;
    }
}
