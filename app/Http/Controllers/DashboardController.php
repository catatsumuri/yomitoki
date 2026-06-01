<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the capture-first dashboard.
     */
    public function index(Request $request): Response
    {
        return $this->renderDashboard($request, $request->user(), null);
    }

    /**
     * Show a top-level scrap by slug inside the dashboard workspace.
     */
    public function show(Request $request, string $slug): Response
    {
        return $this->showWorkspaceScrap($request, $slug);
    }

    private function showWorkspaceScrap(Request $request, string $slug): Response
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

        $relatedScraps = $status === 'active'
            ? $selectedScrap->relatedScraps()
            : collect();
        $latestBackup = $this->findLatestBackup($request->user()->id, $selectedScrap->slug ?? (string) $selectedScrap->id);

        return $this->renderDashboard(
            $request,
            $request->user(),
            $selectedScrap,
            $relatedScraps,
            $latestBackup,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLatestBackup(int $userId, string $scrapKey): ?array
    {
        $files = Storage::files("backups/{$userId}");

        $metaFiles = array_filter(
            $files,
            fn (string $f) => str_starts_with(basename($f), $scrapKey.'-') && str_ends_with($f, '.meta.json'),
        );

        if (empty($metaFiles)) {
            return null;
        }

        rsort($metaFiles);

        $content = Storage::get($metaFiles[0]);

        return $content ? json_decode($content, true) : null;
    }

    /**
     * Render dashboard props for the current user.
     *
     * @param  Collection<int, array<string, mixed>>|null  $relatedScraps
     * @param  array<string, mixed>|null  $latestBackup
     */
    private function renderDashboard(
        Request $request,
        User $user,
        ?Scrap $selectedScrap,
        ?Collection $relatedScraps = null,
        ?array $latestBackup = null,
    ): Response {
        $tag = $request->string('tag')->trim()->value();
        $status = $this->resolveStatusFilter($request);
        $scrapQuery = $this->applyStatusFilter(
            DB::table('scraps')
                ->where('user_id', $user->id),
            $status,
        );
        $filteredScrapQuery = $this->applyTagFilter(clone $scrapQuery, $tag);
        $topLevelScrapQuery = $this->applyStatusFilter(
            Scrap::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_id'),
            $status,
        );
        $selectedScrap = $selectedScrap?->status === 'archived' && $status === 'active'
            ? null
            : $selectedScrap;
        $inboxItems = Inertia::scroll(fn () => $this->applyTagFilter(
            $topLevelScrapQuery
                ->with(['children' => fn ($query) => $this->applyStatusFilter($query, $status)
                    ->orderBy('occurred_at')
                    ->orderBy('id')]),
            $tag,
        )
            ->orderByRaw('COALESCE(last_activity_at, occurred_at) DESC')
            ->paginate(5, pageName: 'scraps')
            ->through(fn (Scrap $scrap) => $this->mapScrapForDashboard($scrap)));
        $documentQuery = DB::table('documents')->where('user_id', $user->id);
        $aiRunQuery = DB::table('ai_runs')->where('user_id', $user->id);
        $availableTags = $this->availableTagsForUser($user->id, $status);

        $needsAttention = $status === 'active'
            ? (clone $filteredScrapQuery)
                ->select(['id', 'title', 'source_type', 'summary', 'status', 'extracted_data', 'occurred_at'])
                ->where('status', 'raw')
                ->latest('occurred_at')
                ->limit(4)
                ->get()
                ->map(function (object $scrap): array {
                    $extractedData = json_decode((string) $scrap->extracted_data, true);

                    return [
                        'id' => $scrap->id,
                        'title' => $scrap->title,
                        'sourceType' => $scrap->source_type,
                        'summary' => $scrap->summary,
                        'status' => $scrap->status,
                        'priority' => $extractedData['priority'] ?? null,
                        'occurredAt' => $scrap->occurred_at,
                    ];
                })
            : collect();

        $recentDocuments = (clone $documentQuery)
            ->select(['id', 'title', 'document_type', 'status', 'summary', 'updated_at'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (object $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'documentType' => $document->document_type,
                'status' => $document->status,
                'summary' => $document->summary,
                'updatedAt' => $document->updated_at,
            ]);

        $sourceOverview = DB::table('scrap_sources')
            ->where('user_id', $user->id)
            ->select(['source_type', DB::raw('count(*) as total')])
            ->groupBy('source_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $source) => [
                'sourceType' => $source->source_type,
                'total' => $source->total,
            ]);

        $recentAiRuns = (clone $aiRunQuery)
            ->select(['id', 'run_type', 'status', 'provider', 'model', 'completed_at', 'created_at'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (object $run) => [
                'id' => $run->id,
                'runType' => $run->run_type,
                'status' => $run->status,
                'provider' => $run->provider,
                'model' => $run->model,
                'completedAt' => $run->completed_at,
                'createdAt' => $run->created_at,
            ]);

        return Inertia::render('dashboard', [
            'summary' => [
                'inboxCount' => $status === 'active'
                    ? (clone $filteredScrapQuery)->where('status', 'raw')->count()
                    : (clone $filteredScrapQuery)->whereNull('parent_id')->count(),
                'attentionCount' => $status === 'active'
                    ? (clone $filteredScrapQuery)->where('status', 'raw')->count()
                    : 0,
                'documentCount' => (clone $documentQuery)->count(),
                'runningAiCount' => (clone $aiRunQuery)->whereIn('status', ['queued', 'running'])->count(),
            ],
            'inboxItems' => $inboxItems,
            'availableTags' => $availableTags,
            'activeTag' => $tag !== '' ? $tag : null,
            'activeStatus' => $status,
            'selectedScrap' => $selectedScrap
                ? array_merge($this->mapScrapForDashboard($selectedScrap), ['latestBackup' => $latestBackup])
                : null,
            'relatedScraps' => $relatedScraps?->values()->all() ?? [],
            'needsAttention' => $needsAttention,
            'recentDocuments' => $recentDocuments,
            'sourceOverview' => $sourceOverview,
            'recentAiRuns' => $recentAiRuns,
        ]);
    }

    /**
     * Transform a scrap for dashboard consumption.
     *
     * @return array<string, mixed>
     */
    private function mapScrapForDashboard(Scrap $scrap): array
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
                'content' => $child->content,
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
     * @return list<array{tag: string, count: int}>
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
            ->countBy()
            ->sortByDesc(fn (int $count) => $count)
            ->map(fn (int $count, string $tag) => ['tag' => $tag, 'count' => $count])
            ->values()
            ->all();
    }
}
