<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class SearchScrapAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
あなたは個人のスクラップコレクションのナレッジアシスタントです。
提供された関連スクラップの内容をもとに、ユーザーの質問に日本語で簡潔に回答してください。
回答には、参照したスクラップのタイトルを明示してください。
関連スクラップが提供されていない場合は、見つからなかった旨を正直に伝えてください。
TEXT;
    }
}
