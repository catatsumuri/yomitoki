<?php

namespace App\Support;

use App\Models\Scrap;
use Illuminate\Support\Str;

class ScrapSlugHelper
{
    public static function makeUnique(?string $candidate, string $fallbackTitle, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($candidate ?: $fallbackTitle);

        if ($baseSlug === '') {
            $baseSlug = 'untitled-scrap';
        }

        $baseSlug = Str::limit($baseSlug, 80, '');
        $slug = $baseSlug;
        $counter = 2;

        while (
            Scrap::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $suffix = '-'.$counter;
            $slug = Str::limit($baseSlug, 80 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $slug;
    }

    /**
     * Collect the scrap id and all descendant ids using iterative BFS.
     *
     * @return array<int, int>
     */
    public static function collectDescendantIds(Scrap $scrap): array
    {
        $queued = [$scrap->id];
        $collected = [];

        while ($queued !== []) {
            $current = $queued;
            $queued = [];
            $collected = [...$collected, ...$current];

            $queued = Scrap::query()
                ->whereIn('parent_id', $current)
                ->pluck('id')
                ->all();
        }

        return array_values(array_unique($collected));
    }
}
