<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ImportScrapRequest;
use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapSummaryJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use Illuminate\Http\JsonResponse;
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

        $slugCandidate = $validated['slug'] ?? ($frontmatter['slug'] ?? null);
        $title = blank($slugCandidate) ? ($frontmatter['title'] ?? null) : null;
        $title = $title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $resolvedSlug = $this->makeUniqueSlug($slugCandidate, $title);

        $tags = collect($frontmatter['tags'] ?? [])
            ->filter(fn (mixed $t) => is_string($t) && $t !== '')
            ->values()
            ->all();

        $project = $validated['project'] ?? null;
        if ($project && ! in_array($project, $tags)) {
            $tags[] = $project;
        }

        $scrap = Scrap::create([
            'user_id' => $request->user()->id,
            'source_type' => 'note',
            'title' => $title,
            'slug' => $resolvedSlug,
            'content' => $content,
            'content_markdown' => $content,
            'status' => 'raw',
            'occurred_at' => now(),
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
