<?php

namespace App\Jobs;

use App\Ai\Agents\GenerateScrapTagsAgent;
use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateScrapTagsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $scrapId) {}

    public function handle(): void
    {
        $scrap = Scrap::find($this->scrapId);

        if (! $scrap || empty($scrap->content)) {
            return;
        }

        $data = GenerateScrapTagsAgent::make()->prompt($scrap->content)->toArray();
        $tags = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn (string $t) => trim($t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $scrap->update([
            'meta' => [
                ...($scrap->meta ?? []),
                'tags' => $tags,
            ],
        ]);
    }
}
