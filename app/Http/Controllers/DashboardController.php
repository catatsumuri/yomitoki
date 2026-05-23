<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use App\Models\User;
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
        return $this->renderDashboard($request->user(), null);
    }

    /**
     * Show a top-level scrap by slug inside the dashboard workspace.
     */
    public function show(Request $request, string $slug): Response
    {
        $selectedScrap = Scrap::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->where('status', '!=', 'archived')
            ->where('slug', $slug)
            ->with(['children' => fn ($query) => $query
                ->where('status', '!=', 'archived')
                ->orderBy('occurred_at')
                ->orderBy('id')])
            ->firstOrFail();

        $relatedScraps = $selectedScrap->relatedScraps();
        $latestBackup = $this->findLatestBackup($request->user()->id, $selectedScrap->slug ?? (string) $selectedScrap->id);

        return $this->renderDashboard($request->user(), $selectedScrap, $relatedScraps, $latestBackup);
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
    private function renderDashboard(User $user, ?Scrap $selectedScrap, ?Collection $relatedScraps = null, ?array $latestBackup = null): Response
    {
        $scrapQuery = DB::table('scraps')
            ->where('user_id', $user->id)
            ->where('status', '!=', 'archived');
        $documentQuery = DB::table('documents')->where('user_id', $user->id);
        $aiRunQuery = DB::table('ai_runs')->where('user_id', $user->id);

        $inboxItems = Inertia::scroll(fn () => Scrap::query()
            ->where('user_id', $user->id)
            ->whereNull('parent_id')
            ->where('status', '!=', 'archived')
            ->with(['children' => fn ($query) => $query
                ->where('status', '!=', 'archived')
                ->orderBy('occurred_at')
                ->orderBy('id')])
            ->latest('occurred_at')
            ->paginate(5, pageName: 'scraps')
            ->through(fn (Scrap $scrap) => $this->mapScrapForDashboard($scrap)));

        $needsAttention = (clone $scrapQuery)
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
            });

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
                'inboxCount' => (clone $scrapQuery)->where('status', 'raw')->count(),
                'attentionCount' => (clone $scrapQuery)->where('status', 'raw')->count(),
                'documentCount' => (clone $documentQuery)->count(),
                'runningAiCount' => (clone $aiRunQuery)->whereIn('status', ['queued', 'running'])->count(),
            ],
            'inboxItems' => $inboxItems,
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
        return [
            'id' => $scrap->id,
            'parentId' => $scrap->parent_id,
            'title' => $scrap->title,
            'slug' => $scrap->slug,
            'content' => $scrap->content,
            'sourceType' => $scrap->source_type,
            'status' => $scrap->status,
            'summary' => $scrap->summary,
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
}
