<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class SummarizeScrapAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
You process a software development plan written in Markdown.
Write a polished, concise title (max 72 characters) that captures the essence of the plan. Preserve important proper nouns and established technical terms from the source text.
Write a concise summary in 1–3 sentences in natural Japanese. Focus on what is being built and why. Do not include implementation details. If the source text is not in Japanese, translate its meaning into Japanese.
Return only the structured output.
TEXT;
    }

    /**
     * Get the structured output schema.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }
}
