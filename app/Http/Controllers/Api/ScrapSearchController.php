<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

class ScrapSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->currentAccessToken()?->can('ingest'), 403);

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'source_type' => ['sometimes', 'nullable', 'string'],
            'threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'include' => ['sometimes', 'nullable', 'string', 'in:content'],
        ]);

        $q = $validated['q'];
        $limit = (int) ($validated['limit'] ?? 5);
        $sourceType = $validated['source_type'] ?? null;
        $threshold = (float) ($validated['threshold'] ?? 0.2);
        $includeContent = ($validated['include'] ?? null) === 'content';

        if (DB::getDriverName() !== 'pgsql') {
            return response()->json(['query' => $q, 'data' => []]);
        }

        $response = Embeddings::for([$q])->generate();
        $vector = json_encode($response->first());

        $sourceFilter = $sourceType !== null ? 'AND source_type = ?' : '';
        $bindings = [$vector, $request->user()->id];
        if ($sourceType !== null) {
            $bindings[] = $sourceType;
        }
        $bindings = array_merge($bindings, [$vector, $threshold, $limit]);

        $rows = DB::select(
            <<<SQL
            SELECT id, slug, title, source_type, status, summary, occurred_at,
                   content_markdown,
                   1 - (embedding <=> ?::vector) AS similarity
            FROM scraps
            WHERE user_id = ?
              AND embedding IS NOT NULL
              AND status != 'archived'
              AND parent_id IS NULL
              $sourceFilter
              AND 1 - (embedding <=> ?::vector) >= ?
            ORDER BY similarity DESC
            LIMIT ?
            SQL,
            $bindings,
        );

        $data = collect($rows)->map(function (object $row) use ($includeContent): array {
            $item = [
                'id' => $row->id,
                'slug' => $row->slug,
                'title' => $row->title,
                'source_type' => $row->source_type,
                'status' => $row->status,
                'summary' => $row->summary,
                'similarity' => round((float) $row->similarity, 3),
                'occurred_at' => $row->occurred_at,
                'url' => route('dashboard.show', ['slug' => $row->slug]),
            ];

            if ($includeContent) {
                $item['content_markdown'] = $row->content_markdown;
            }

            return $item;
        })->all();

        return response()->json(['query' => $q, 'data' => $data]);
    }
}
