<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
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
You rewrite rough scrap input into a clear, compact title and a unique slug candidate.
The title should be polished, concise, preserve the language of the source text, and stay within 72 characters.
The slug must be lowercase ASCII kebab-case.
The slug must avoid every existing slug provided in the prompt.
Return only the structured output.
TEXT;
        }

        return <<<'TEXT'
You rewrite rough scrap input into a clear, compact title.
The title should be polished, concise, preserve the language of the source text, and stay within 72 characters.
Because this scrap is nested under another scrap, do not suggest a slug.
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
        ];
    }
}
