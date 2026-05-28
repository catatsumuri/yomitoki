<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SearchScrapAgent;
use App\Http\Requests\SearchQueryRequest;
use App\Models\Scrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Responses\StreamableAgentResponse;

class SearchController extends Controller
{
    /**
     * Show the search page with a fresh conversation.
     */
    public function index(Request $request): Response
    {
        $conversationId = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $request->user()->id,
            'title' => 'スクラップ検索',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Inertia::render('search', [
            'conversationId' => $conversationId,
        ]);
    }

    /**
     * Create a new conversation and return its ID as JSON.
     */
    public function init(Request $request): JsonResponse
    {
        $conversationId = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => $request->user()->id,
            'title' => 'スクラップ検索',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['conversation_id' => $conversationId]);
    }

    /**
     * Handle a search query and stream the response.
     */
    public function query(SearchQueryRequest $request): StreamableAgentResponse
    {
        $validated = $request->validated();
        $query = $validated['query'];
        $conversationId = $validated['conversation_id'];

        $relatedScrapsQuery = Scrap::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->where('status', '!=', 'archived')
            ->whereNotNull('embedding');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $relatedScrapsQuery->whereVectorSimilarTo('embedding', $query, 0.2);
        }

        $relatedScraps = $relatedScrapsQuery
            ->latest()
            ->limit(5)
            ->get(['title', 'slug', 'summary', 'content_markdown']);

        $prompt = $this->buildContextualPrompt($query, $relatedScraps);

        return SearchScrapAgent::make()
            ->continue($conversationId, as: $request->user())
            ->stream($prompt)
            ->usingVercelDataProtocol();
    }

    /**
     * Build a contextual prompt by injecting retrieved scraps.
     *
     * @param  Collection<int, Scrap>  $scraps
     */
    private function buildContextualPrompt(string $query, Collection $scraps): string
    {
        if ($scraps->isEmpty()) {
            return $query;
        }

        $context = $scraps
            ->map(function (Scrap $scrap) {
                $header = $scrap->slug
                    ? '### ['.$scrap->title.']('.route('dashboard.show', $scrap->slug).')'
                    : '### '.$scrap->title;

                return $header."\n".($scrap->summary ?? $scrap->content_markdown);
            })
            ->implode("\n\n");

        return <<<TEXT
関連スクラップ:
{$context}

---
質問: {$query}
TEXT;
    }
}
