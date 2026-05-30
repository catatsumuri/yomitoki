<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateScrapRequest;
use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Support\ScrapSlugHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScrapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $scraps = Scrap::query()
            ->where('user_id', $request->user()->id)
            ->when($request->query('source_type'), fn ($q, $v) => $q->where('source_type', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('occurred_at')
            ->paginate(min((int) ($request->query('limit', 20)), 100));

        return response()->json([
            'data' => $scraps->map(fn (Scrap $scrap) => [
                'id' => $scrap->id,
                'slug' => $scrap->slug,
                'title' => $scrap->title,
                'source_type' => $scrap->source_type,
                'status' => $scrap->status,
                'created_at' => $scrap->created_at,
            ]),
            'meta' => [
                'total' => $scraps->total(),
                'per_page' => $scraps->perPage(),
                'current_page' => $scraps->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $scrap = Scrap::query()
            ->where('slug', $slug)
            ->where('user_id', $request->user()->id)
            ->with('children')
            ->firstOrFail();

        return response()->json($this->formatScrap($scrap, withChildren: true));
    }

    public function update(UpdateScrapRequest $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $scrap = Scrap::query()
            ->where('slug', $slug)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validated();
        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['title'] = $validated['title'];
        }

        if (array_key_exists('slug', $validated)) {
            $updateData['slug'] = ScrapSlugHelper::makeUnique($validated['slug'], $validated['title'] ?? $scrap->title, $scrap->id);
        }

        if (isset($validated['content_markdown'])) {
            $updateData['content'] = $validated['content_markdown'];
            $updateData['content_markdown'] = $validated['content_markdown'];
        }

        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }

        if (isset($validated['source_type'])) {
            $updateData['source_type'] = $validated['source_type'];
        }

        if (array_key_exists('summary', $validated)) {
            $updateData['summary'] = $validated['summary'];
        }

        $scrap->update($updateData);

        if (isset($updateData['content_markdown'])) {
            GenerateScrapEmbeddingJob::dispatch($scrap->id);
            SummarizeScrapJob::dispatch($scrap->id);
        }

        return response()->json($this->formatScrap($scrap->fresh()));
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $scrap = Scrap::query()
            ->where('slug', $slug)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ids = ScrapSlugHelper::collectDescendantIds($scrap);

        Scrap::whereIn('id', $ids)->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function formatScrap(Scrap $scrap, bool $withChildren = false): array
    {
        $data = [
            'id' => $scrap->id,
            'slug' => $scrap->slug,
            'title' => $scrap->title,
            'source_type' => $scrap->source_type,
            'status' => $scrap->status,
            'summary' => $scrap->summary,
            'content_markdown' => $scrap->content_markdown,
            'occurred_at' => $scrap->occurred_at,
            'created_at' => $scrap->created_at,
            'updated_at' => $scrap->updated_at,
        ];

        if ($withChildren) {
            $data['children'] = $scrap->children->map(fn (Scrap $child) => $this->formatScrap($child))->values()->all();
        }

        return $data;
    }
}
