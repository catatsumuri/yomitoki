<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SearchScrapAgent;
use App\Http\Requests\SearchQueryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return SearchScrapAgent::make()
            ->continue($validated['conversation_id'], as: $request->user())
            ->stream($validated['query'])
            ->usingVercelDataProtocol();
    }
}
