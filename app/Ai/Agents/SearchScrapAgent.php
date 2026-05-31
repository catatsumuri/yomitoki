<?php

namespace App\Ai\Agents;

use App\Models\Scrap;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;
use Stringable;

#[UseCheapestModel]
class SearchScrapAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
あなたは個人のスクラップコレクションのナレッジアシスタントです。
ユーザーの質問に答えるため、必ずsearch_scrapsツールを使って関連スクラップを検索してください。
検索結果をもとに、日本語で簡潔に回答してください。
回答には、参照したスクラップのタイトルを明示してください。
関連スクラップが見つからない場合は、見つからなかった旨を正直に伝えてください。
TEXT;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        $userId = Auth::id();

        return [
            (new SimilaritySearch(function (string $queryString) use ($userId) {
                return Scrap::query()
                    ->where('user_id', $userId)
                    ->whereNull('parent_id')
                    ->where('status', '!=', 'archived')
                    ->whereNotNull('embedding')
                    ->whereVectorSimilarTo('embedding', $queryString, 0.2)
                    ->limit(5)
                    ->get(['title', 'slug', 'summary'])
                    ->map(fn (Scrap $scrap) => [
                        'title' => $scrap->title,
                        'slug' => $scrap->slug,
                        'summary' => $scrap->summary,
                        'url' => route('dashboard.show', $scrap->slug),
                    ]);
            }))->withDescription('ユーザーのスクラップをベクトル検索します。'),
        ];
    }
}
