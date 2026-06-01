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
Generate 1–5 short tags in Japanese that best describe its topic and type.
Tags should be lowercase, concise (1–3 characters preferred, 8 characters max), and reusable across scraps.
Examples: ログイン, バグ, DB, API, 仕様, インシデント, 認証, パフォーマンス, セキュリティ
Return only the structured output.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tags' => $schema->array($schema->string())->required(),
        ];
    }
}
