<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapTagsJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:embed {--user= : User ID or email (default: all users)} {--tags : Also dispatch tag generation jobs} {--force : Dispatch even if embedding already exists}')]
#[Description('Dispatch embedding (and optionally tag) generation jobs for scraps missing them.')]
class EmbedScrapsCommand extends Command
{
    public function handle(): int
    {
        $query = Scrap::query()->whereNull('parent_id');

        if (! $this->option('force')) {
            $query->whereNull('embedding');
        }

        if ($userId = $this->option('user')) {
            $user = is_numeric($userId)
                ? User::find($userId)
                : User::where('email', $userId)->first();

            if (! $user) {
                $this->error("User not found: {$userId}");

                return self::FAILURE;
            }

            $query->where('user_id', $user->id);
        }

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No scraps to process.');

            return self::SUCCESS;
        }

        $withTags = $this->option('tags');
        $bar = $this->output->createProgressBar($ids->count());
        $bar->start();

        foreach ($ids as $id) {
            GenerateScrapEmbeddingJob::dispatch($id);

            if ($withTags) {
                GenerateScrapTagsJob::dispatch($id);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(
            "Dispatched {$ids->count()} embedding job(s)"
            .($withTags ? " + {$ids->count()} tag job(s)" : '')
            .'. Run the queue worker to process them.'
        );

        return self::SUCCESS;
    }
}
