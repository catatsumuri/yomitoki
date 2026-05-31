<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\SuggestScrapMetadataAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportScrapRequest;
use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapSummaryJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class ImportScrapController extends Controller
{
    public function __invoke(ImportScrapRequest $request): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $validated = $request->validated();
        $file = $request->file('file');
        $rawContent = $file->get();

        ['frontmatter' => $frontmatter, 'content' => $content] = $this->parseFrontmatter($rawContent);

        $explicitSlug = $validated['slug'] ?? ($frontmatter['slug'] ?? null);
        $title = $frontmatter['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $slugCandidate = blank($explicitSlug)
            ? $this->suggestSlug($title, $content)
            : $explicitSlug;

        $resolvedSlug = $this->makeUniqueSlug($slugCandidate, $title);

        $rawTags = $frontmatter['tags'] ?? $frontmatter['topics'] ?? [];
        $tags = collect(is_array($rawTags) ? $rawTags : [$rawTags])
            ->filter(fn (mixed $t) => is_string($t) && $t !== '')
            ->values()
            ->all();

        $project = $validated['project'] ?? null;
        if ($project && ! in_array($project, $tags)) {
            $tags[] = $project;
        }

        $occurredAt = $this->resolveOccurredAt($frontmatter);

        $scrap = Scrap::create([
            'user_id' => $request->user()->id,
            'source_type' => 'note',
            'title' => $title,
            'slug' => $resolvedSlug,
            'content' => $content,
            'content_markdown' => $content,
            'status' => 'raw',
            'occurred_at' => $occurredAt,
            'meta' => array_filter([
                'tags' => $tags,
                'project' => $project,
                'created_from' => 'file-import',
            ]),
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);

        if (isset($frontmatter['title'])) {
            GenerateScrapSummaryJob::dispatch($scrap->id);
        } else {
            SummarizeScrapJob::dispatch($scrap->id);
        }

        return response()->json([
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'url' => route('dashboard.show', ['slug' => $scrap->slug]),
        ], 201);
    }

    /**
     * Ask the AI for a slug candidate, falling back to Str::slug(title) on failure.
     */
    private function suggestSlug(string $title, string $content): string
    {
        $existingSlugs = Scrap::query()
            ->whereNotNull('slug')
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $prompt = implode("\n\n", [
            'Suggest a unique lowercase ASCII kebab-case slug for the following content.',
            'The slug must not appear in this list: '.implode(', ', $existingSlugs ?: ['(none)']),
            "Title: {$title}",
            'Content (excerpt): '.Str::limit($content, 500),
        ]);

        try {
            $response = SuggestScrapMetadataAgent::make(shouldSuggestSlug: true)
                ->prompt($prompt)
                ->toArray();

            return $response['slug'] ?? Str::slug($title);
        } catch (\Throwable) {
            return Str::slug($title);
        }
    }

    /**
     * Resolve occurred_at from frontmatter created/date fields, falling back to now().
     *
     * @param  array<string, mixed>  $frontmatter
     */
    private function resolveOccurredAt(array $frontmatter): Carbon
    {
        foreach (['created', 'date', 'created_at'] as $key) {
            $value = $frontmatter[$key] ?? null;

            if (blank($value)) {
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value);
            }

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return Carbon::createFromTimestamp((int) $value);
            }

            try {
                return Carbon::parse((string) $value);
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::now();
    }

    /**
     * @return array{frontmatter: array<string, mixed>, content: string}
     */
    private function parseFrontmatter(string $raw): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)/s', $raw, $matches)) {
            try {
                $frontmatter = Yaml::parse($matches[1]);
            } catch (\Exception) {
                $frontmatter = [];
            }

            return [
                'frontmatter' => is_array($frontmatter) ? $frontmatter : [],
                'content' => ltrim($matches[2]),
            ];
        }

        return ['frontmatter' => [], 'content' => $raw];
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
