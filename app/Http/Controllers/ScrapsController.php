<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScrapsController extends Controller
{
    /**
     * Show the article list, or the backup history when ?view=backups.
     */
    public function index(Request $request): Response
    {
        if ($request->string('view')->value() === 'backups') {
            return $this->renderBackups($request->user());
        }

        return $this->renderArticles($request, $request->user(), null);
    }

    /**
     * Show a top-level scrap by slug in the article detail view.
     */
    public function show(Request $request, string $slug): Response
    {
        $tag = $request->string('tag')->trim()->value();
        $status = $this->resolveStatusFilter($request);

        $selectedScrap = Scrap::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->when(
                $status === 'archived',
                fn (EloquentBuilder $query) => $query->where('status', 'archived'),
                fn (EloquentBuilder $query) => $query->where('status', '!=', 'archived'),
            )
            ->where('slug', $slug)
            ->when($tag !== '', fn (EloquentBuilder $query) => $this->applyTagFilter($query, $tag))
            ->with(['children' => fn ($query) => $query
                ->when(
                    $status === 'archived',
                    fn (EloquentBuilder $childQuery) => $childQuery->where('status', 'archived'),
                    fn (EloquentBuilder $childQuery) => $childQuery->where('status', '!=', 'archived'),
                )
                ->orderBy('occurred_at')
                ->orderBy('id')])
            ->firstOrFail();

        return $this->renderArticles($request, $request->user(), $selectedScrap);
    }

    /**
     * Render the backup history view.
     */
    private function renderBackups(User $user): Response
    {
        $files = Storage::files("backups/{$user->id}");

        $metaPaths = array_filter($files, fn (string $f) => str_ends_with($f, '.meta.json'));

        $backups = collect($metaPaths)
            ->map(function (string $metaPath): ?array {
                $content = Storage::get($metaPath);
                if (! $content) {
                    return null;
                }

                $meta = json_decode($content, true);
                if (! is_array($meta)) {
                    return null;
                }

                $zipPath = Str::beforeLast($metaPath, '.meta.json');
                $fileSize = Storage::exists($zipPath) ? Storage::size($zipPath) : 0;

                return [
                    'filename' => basename($zipPath),
                    'scrapSlug' => $meta['scrapSlug'] ?? null,
                    'createdAt' => $meta['createdAt'] ?? null,
                    'description' => $meta['description'] ?? null,
                    'fileSize' => $fileSize,
                ];
            })
            ->filter()
            ->sortByDesc('createdAt')
            ->values();

        $slugs = $backups->pluck('scrapSlug')->filter()->unique()->values()->all();

        $scrapTitles = Scrap::query()
            ->where('user_id', $user->id)
            ->whereIn('slug', $slugs)
            ->pluck('title', 'slug');

        $backups = $backups->map(function (array $backup) use ($scrapTitles): array {
            $backup['scrapTitle'] = $scrapTitles[$backup['scrapSlug']] ?? null;

            return $backup;
        });

        return Inertia::render('scraps', [
            'view' => 'backups',
            'backups' => $backups->values()->all(),
            'scraps' => ['data' => []],
            'availableTags' => [],
            'activeTag' => null,
            'activeStatus' => 'active',
            'selectedScrap' => null,
        ]);
    }

    private function renderArticles(Request $request, User $user, ?Scrap $selectedScrap): Response
    {
        $tag = $request->string('tag')->trim()->value();
        $status = $this->resolveStatusFilter($request);

        $topLevelScrapQuery = $this->applyStatusFilter(
            Scrap::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_id'),
            $status,
        );

        $selectedScrap = $selectedScrap?->status === 'archived' && $status === 'active'
            ? null
            : $selectedScrap;

        $scraps = Inertia::scroll(fn () => $this->applyTagFilter(
            $topLevelScrapQuery
                ->with(['children' => fn ($query) => $this->applyStatusFilter($query, $status)
                    ->orderBy('occurred_at')
                    ->orderBy('id')]),
            $tag,
        )
            ->latest('occurred_at')
            ->paginate(5, pageName: 'scraps')
            ->through(fn (Scrap $scrap) => $this->mapScrap($scrap)));

        return Inertia::render('scraps', [
            'view' => 'list',
            'scraps' => $scraps,
            'availableTags' => $this->availableTagsForUser($user->id, $status),
            'activeTag' => $tag !== '' ? $tag : null,
            'activeStatus' => $status,
            'selectedScrap' => $selectedScrap ? $this->mapScrap($selectedScrap) : null,
            'backups' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapScrap(Scrap $scrap): array
    {
        $meta = is_array($scrap->meta) ? $scrap->meta : [];

        return [
            'id' => $scrap->id,
            'parentId' => $scrap->parent_id,
            'title' => $scrap->title,
            'slug' => $scrap->slug,
            'content' => $scrap->content,
            'sourceType' => $scrap->source_type,
            'status' => $scrap->status,
            'summary' => $scrap->summary,
            'tags' => collect($meta['tags'] ?? [])
                ->filter(fn (mixed $tag) => is_string($tag) && $tag !== '')
                ->values()
                ->all(),
            'occurredAt' => $scrap->occurred_at?->toIso8601String(),
            'children' => $scrap->children->map(fn (Scrap $child) => [
                'id' => $child->id,
                'parentId' => $child->parent_id,
                'title' => $child->title,
                'sourceType' => $child->source_type,
                'status' => $child->status,
                'summary' => $child->summary,
                'occurredAt' => $child->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function applyTagFilter(QueryBuilder|EloquentBuilder $query, string $tag): QueryBuilder|EloquentBuilder
    {
        if ($tag === '') {
            return $query;
        }

        return $query->whereJsonContains('meta->tags', $tag);
    }

    private function applyStatusFilter(QueryBuilder|EloquentBuilder|HasMany $query, string $status): QueryBuilder|EloquentBuilder|HasMany
    {
        if ($status === 'archived') {
            return $query->where('status', 'archived');
        }

        return $query->where('status', '!=', 'archived');
    }

    private function resolveStatusFilter(Request $request): string
    {
        return $request->string('status')->value() === 'archived'
            ? 'archived'
            : 'active';
    }

    /**
     * @return list<string>
     */
    private function availableTagsForUser(int $userId, string $status): array
    {
        return $this->applyStatusFilter(
            DB::table('scraps')
                ->where('user_id', $userId)
                ->whereNull('parent_id'),
            $status,
        )
            ->whereNotNull('meta')
            ->pluck('meta')
            ->flatMap(function (mixed $meta): array {
                $decoded = json_decode((string) $meta, true);
                $tags = $decoded['tags'] ?? [];

                if (! is_array($tags)) {
                    return [];
                }

                return array_values(array_filter($tags, fn (mixed $tag) => is_string($tag) && $tag !== ''));
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
