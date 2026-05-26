<?php

namespace App\Jobs;

use App\Ai\Agents\SummarizeScrapAgent;
use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SummarizeScrapJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap || empty($scrap->content_markdown)) {
            return;
        }

        $response = SummarizeScrapAgent::make()->prompt($scrap->content_markdown);

        $scrap->update([
            'title' => $response['title'] ?? $scrap->title,
            'summary' => $response['summary'] ?? null,
        ]);
    }
}
