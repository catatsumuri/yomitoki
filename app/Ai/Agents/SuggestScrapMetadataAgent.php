<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class SuggestScrapMetadataAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    private const TITLE_CHARACTER_LIMIT = 72;

    public function __construct(
        public bool $shouldSuggestSlug = true,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        if ($this->shouldSuggestSlug) {
            return <<<'TEXT'
You rewrite rough scrap input into a clear, compact title, a unique slug candidate, and a concise summary.
The title should be polished, concise, preserve the language of the source text, and stay within 72 characters.
The slug must be lowercase ASCII kebab-case and avoid every existing slug provided in the prompt.
The summary should be 1–3 sentences in natural Japanese describing what the scrap is about and why it matters.
Return only the structured output.
TEXT;
        }

        return <<<'TEXT'
You rewrite rough scrap input into a clear, compact title and a concise summary.
The title should be polished, concise, preserve the language of the source text, and stay within 72 characters.
Because this scrap is nested under another scrap, do not suggest a slug.
The summary should be 1–3 sentences in natural Japanese describing what the scrap is about and why it matters.
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
            'slug' => $schema->string()->nullable()->required(),
            'summary' => $schema->string()->required(),
        ];
    }
}
