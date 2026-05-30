<?php

namespace App\Jobs;

use App\Ai\Agents\RefineScrapMarkdownAgent;
use App\Models\Scrap;
use App\Models\ScrapRevision;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefineScrapMarkdownJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap || empty($scrap->content)) {
            return;
        }

        ScrapRevision::create([
            'scrap_id' => $scrap->id,
            'content' => $scrap->content,
        ]);

        $response = RefineScrapMarkdownAgent::make()->prompt($scrap->content);

        $scrap->update([
            'content' => $response['content'],
            'content_markdown' => $response['content'],
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);
    }
}
