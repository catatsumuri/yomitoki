<?php

namespace App\Jobs;

use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

class GenerateScrapEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    private function stripCodeBlocks(string $content): string
    {
        return trim(preg_replace('/```[\s\S]*?```/m', '', $content));
    }

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap) {
            return;
        }

        $body = $scrap->summary
            ? $scrap->summary
            : $this->stripCodeBlocks($scrap->content ?? '');

        $text = Str::limit(
            implode("\n\n", array_filter([$scrap->title, $body])),
            20000,
            '',
        );

        $response = Embeddings::for([$text])->generate();

        $scrap->update([
            'embedding' => json_encode($response->first()),
            'embedding_model' => $response->meta->model,
            'embedding_generated_at' => now(),
        ]);
    }
}
