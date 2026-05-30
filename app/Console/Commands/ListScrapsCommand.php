<?php

namespace App\Console\Commands;

use App\Models\Scrap;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scraps:list {--source-type= : Filter by source type} {--status= : Filter by status} {--json : Output as JSON array} {--slugs-only : Output slugs only, one per line} {--limit=20 : Maximum number of results}')]
#[Description('List scraps from the database')]
class ListScrapsCommand extends Command
{
    public function handle(): int
    {
        $scraps = Scrap::query()
            ->when($this->option('source-type'), fn ($q, $v) => $q->where('source_type', $v))
            ->when($this->option('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('occurred_at')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'slug', 'title', 'source_type', 'status', 'occurred_at']);

        if ($this->option('json')) {
            $this->line(json_encode($scraps->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($this->option('slugs-only')) {
            $scraps->each(fn (Scrap $scrap) => $this->line((string) $scrap->slug));

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Title', 'Source Type', 'Status', 'Occurred At'],
            $scraps->map(fn (Scrap $scrap) => [
                $scrap->slug ?? '',
                $scrap->title ?? '',
                $scrap->source_type,
                $scrap->status,
                $scrap->occurred_at?->format('Y-m-d H:i') ?? '',
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}
