<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestScrapRequest;
use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class IngestScrapController extends Controller
{
    public function __invoke(IngestScrapRequest $request): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $validated = $request->validated();
        $resolvedSlug = $this->makeUniqueSlug(
            candidate: $validated['slug'] ?? null,
            fallbackTitle: $validated['title'],
        );

        $scrap = Scrap::create([
            'user_id' => $request->user()->id,
            'source_type' => $validated['source_type'] ?? 'plan',
            'title' => $validated['title'],
            'slug' => $resolvedSlug,
            'content' => $validated['content_markdown'],
            'content_markdown' => $validated['content_markdown'],
            'status' => 'processed',
            'occurred_at' => now(),
            'meta' => array_filter([
                ...($validated['meta'] ?? []),
                'project' => $validated['project'] ?? null,
                'directory' => $validated['directory'] ?? null,
                'created_from' => 'external-ingest-api',
                'tags' => array_values(array_filter([$validated['project'] ?? null])),
            ]),
        ]);

        SummarizeScrapJob::dispatch($scrap->id);

        if (config('services.embedding.enabled', true)) {
            GenerateScrapEmbeddingJob::dispatch($scrap->id);
        }

        return response()->json([
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'url' => route('dashboard.show', ['slug' => $scrap->slug]),
        ], 201);
    }

    private function makeUniqueSlug(?string $candidate, string $fallbackTitle): string
    {
        $baseSlug = Str::slug($candidate ?: $fallbackTitle);

        if ($baseSlug === '') {
            $baseSlug = 'untitled-scrap';
        }

        $baseSlug = Str::limit($baseSlug, 80, '');
        $slug = $baseSlug;
        $counter = 2;

        while (Scrap::where('slug', $slug)->exists()) {
            $suffix = '-'.$counter;
            $slug = Str::limit($baseSlug, 80 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $slug;
    }
}
