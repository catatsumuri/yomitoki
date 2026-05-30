<?php

namespace App\Jobs;

use App\Ai\Agents\GenerateScrapSummaryAgent;
use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateScrapSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap || empty($scrap->content)) {
            return;
        }

        $data = GenerateScrapSummaryAgent::make()->prompt($scrap->content)->toArray();

        $scrap->update(['summary' => $data['summary']]);
    }
}
