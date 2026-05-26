<?php

namespace App\Jobs;

use App\Ai\Agents\ComposeDocumentAgent;
use App\Models\AiRun;
use App\Models\Document;
use App\Models\Scrap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ComposeDocumentJob implements ShouldQueue
{
    use Queueable;

    /** Allow up to 5 minutes for the AI call to complete. */
    public int $timeout = 300;

    /** Do not retry on timeout — mark as failed immediately. */
    public int $tries = 1;

    public function __construct(
        public readonly int $userId,
        public readonly array $scrapIds,
        public readonly string $documentType = 'spec',
        public readonly ?int $aiRunId = null,
    ) {}

    public function handle(): void
    {
        if ($this->aiRunId) {
            AiRun::where('id', $this->aiRunId)->update([
                'status' => 'running',
                'started_at' => now(),
            ]);
        }

        $scraps = Scrap::query()
            ->where('user_id', $this->userId)
            ->whereIn('id', $this->scrapIds)
            ->whereNull('parent_id')
            ->where('status', '!=', 'archived')
            ->get()
            ->sortBy(fn (Scrap $scrap) => array_search($scrap->id, $this->scrapIds))
            ->values();

        if ($scraps->isEmpty()) {
            if ($this->aiRunId) {
                AiRun::where('id', $this->aiRunId)->update([
                    'status' => 'failed',
                    'error_message' => 'No valid scraps found for the given IDs.',
                    'completed_at' => now(),
                ]);
            }

            return;
        }

        $prompt = $this->buildPrompt($scraps);

        $response = ComposeDocumentAgent::make()->prompt($prompt);

        $document = Document::create([
            'user_id' => $this->userId,
            'title' => $response['title'],
            'document_type' => $this->documentType,
            'status' => 'draft',
            'content_markdown' => $response['content_markdown'],
            'summary' => $response['summary'],
            'outline' => $response['outline'],
        ]);

        $pivotRows = $scraps->map(fn (Scrap $scrap, int $index) => [
            'document_id' => $document->id,
            'scrap_id' => $scrap->id,
            'position' => $index,
            'role' => 'source',
            'excerpt' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('document_scraps')->insert($pivotRows);

        if ($this->aiRunId) {
            AiRun::where('id', $this->aiRunId)->update([
                'status' => 'completed',
                'result_document_id' => $document->id,
                'output_payload' => $response->toArray(),
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        if ($this->aiRunId) {
            AiRun::where('id', $this->aiRunId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Build the prompt string from the given scraps.
     *
     * @param  Collection<int, Scrap>  $scraps
     */
    private function buildPrompt(Collection $scraps): string
    {
        $typeLabel = match ($this->documentType) {
            'spec' => '仕様書',
            'summary' => '概要ドキュメント',
            'report' => 'レポート',
            default => 'ドキュメント',
        };

        $sections = $scraps->map(function (Scrap $scrap) {
            $title = $scrap->title ?: '（タイトルなし）';
            $body = $scrap->content_markdown ?? $scrap->content ?? '';

            return "## {$title}\n\n{$body}";
        })->implode("\n\n---\n\n");

        return <<<TEXT
以下の {$scraps->count()} 件のプランをもとに、{$typeLabel}を日本語で作成してください。

{$sections}
TEXT;
    }
}
