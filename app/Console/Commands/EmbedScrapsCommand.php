<?php

namespace App\Console\Commands;

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Models\Scrap;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:embed')]
#[Description('Dispatch embedding jobs for all scraps missing embeddings')]
class EmbedScrapsCommand extends Command
{
    public function handle(): void
    {
        $count = Scrap::whereNull('embedding')->count();

        if ($count === 0) {
            $this->info('All scraps already have embeddings.');

            return;
        }

        $this->info("Dispatching embedding jobs for {$count} scraps...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        Scrap::whereNull('embedding')
            ->select('id')
            ->each(function (Scrap $scrap) use ($bar): void {
                GenerateScrapEmbeddingJob::dispatch($scrap->id);
                $bar->advance();
            });

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$count} jobs. Run the queue worker to process them.");
    }
}
