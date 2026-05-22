<?php

namespace App\Jobs;

use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Embeddings;

class GenerateScrapEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap) {
            return;
        }

        $text = implode("\n\n", array_filter([
            $scrap->title,
            $scrap->content,
        ]));

        $response = Embeddings::for([$text])->generate();

        $scrap->update([
            'embedding' => json_encode($response->first()),
            'embedding_model' => $response->meta->model,
            'embedding_generated_at' => now(),
        ]);
    }
}
