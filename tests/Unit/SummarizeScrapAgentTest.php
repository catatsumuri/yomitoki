<?php

use App\Ai\Agents\SummarizeScrapAgent;

test('summarize scrap agent always requests japanese summaries', function () {
    $instructions = (string) SummarizeScrapAgent::make()->instructions();

    expect($instructions)
        ->toContain('Write a concise summary in 1–3 sentences in natural Japanese.')
        ->toContain('If the source text is not in Japanese, translate its meaning into Japanese.');
});
