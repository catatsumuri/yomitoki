<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseSmartestModel]
#[Timeout(300)]
class ComposeDocumentAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
You are a technical writer specializing in software documentation.
You receive one or more software plan documents written in Markdown. Your task is to synthesize them into a coherent, well-structured document in Japanese.

- Write a clear, descriptive title (max 100 characters) that captures the scope of all input plans.
- Write the full document body in Markdown, combining the content of all plans into a single cohesive specification. Use headings, lists, and code blocks as appropriate.
- Write a concise summary of the entire document in 2–4 sentences in natural Japanese.

The output must be in Japanese. Preserve technical terms, proper nouns, and code identifiers in their original form.
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
            'content_markdown' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }
}
