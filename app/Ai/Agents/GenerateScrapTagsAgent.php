<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class GenerateScrapTagsAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
You receive a scrap — a rough note, fragment, or captured thought.
Generate exactly 1–3 category-level tags in Japanese that classify the scrap's topic.

Rules:
- Maximum 3 tags. Fewer is better.
- Use broad category words, not specific proper nouns or product names.
- Avoid: Stripe, PostgreSQL, Redis, Excel, RBAC, Webhook (too specific)
- Prefer: 障害, 認証, パフォーマンス, API, セキュリティ, バグ, 調査, 仕様, 設計
- Each tag: 2–6 characters, reusable across many scraps.
- Separate tags with commas.

Return only the structured output.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tags' => $schema->string()->description('Comma-separated list of tags, e.g. "ログイン,認証,バグ"')->required(),
        ];
    }
}
