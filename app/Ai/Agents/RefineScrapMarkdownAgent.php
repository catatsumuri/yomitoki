<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class RefineScrapMarkdownAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
You receive raw text that may be plain text, rough notes, or poorly formatted content.
Rewrite it as clean, well-structured Markdown.
Preserve all information and meaning — do not summarise or omit anything.
Use appropriate Markdown elements: headings, lists, code blocks, links, bold/italic where they genuinely help readability.
Never use a top-level H1 heading (`# ...`). If the content starts with one, remove it. The scrap title is stored separately.
If the input is already well-formed Markdown, return it with only minor improvements.
Return only the structured output.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->required(),
        ];
    }
}
