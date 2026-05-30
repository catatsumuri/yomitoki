<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SuggestScrapMetadataAgent;
use App\Http\Requests\StoreScrapRequest;
use App\Http\Requests\SuggestScrapMetadataRequest;
use App\Http\Requests\UpdateScrapRequest;
use App\Http\Requests\UploadScrapImageRequest;
use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapSummaryJob;
use App\Jobs\RefineScrapMarkdownJob;
use App\Models\Scrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ScrapController extends Controller
{
    private const TITLE_CHARACTER_LIMIT = 72;

    /**
     * Suggest metadata for a scrap before saving it.
     */
    public function suggestMetadata(SuggestScrapMetadataRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentScrap = isset($validated['scrap_id'])
            ? Scrap::query()
                ->where('user_id', $request->user()->id)
                ->find($validated['scrap_id'])
            : null;

        $shouldSuggestSlug = $currentScrap
            ? $currentScrap->parent_id === null
            : ! isset($validated['parent_id']);

        $existingSlugs = Scrap::query()
            ->whereNotNull('slug')
            ->when(
                $currentScrap !== null,
                fn ($query) => $query->whereKeyNot($currentScrap->id),
            )
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $response = SuggestScrapMetadataAgent::make(
            shouldSuggestSlug: $shouldSuggestSlug,
        )->prompt($this->buildMetadataSuggestionPrompt(
            content: $validated['content'],
            currentTitle: $validated['title'] ?? null,
            currentSlug: $validated['slug'] ?? null,
            existingSlugs: $existingSlugs,
            shouldSuggestSlug: $shouldSuggestSlug,
        ));

        $data = $response->toArray();

        $suggestedTitle = $this->normalizeSuggestedTitle(
            $data['title'] ?? null,
            $validated['content'],
        );

        return response()->json([
            'title' => $suggestedTitle,
            'slug' => $shouldSuggestSlug
                ? $this->makeUniqueSlug(
                    candidate: $data['slug'] ?? null,
                    fallbackTitle: $suggestedTitle,
                    ignoreScrapId: $currentScrap?->id,
                )
                : null,
            'summary' => blank($data['summary'] ?? null) ? null : $data['summary'],
        ]);
    }

    /**
     * Store a newly created scrap.
     */
    public function store(StoreScrapRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $parentScrap = isset($validated['parent_id'])
            ? Scrap::query()
                ->where('user_id', $request->user()->id)
                ->find($validated['parent_id'])
            : null;
        $resolvedTitle = $this->normalizeSuggestedTitle(
            $validated['title'] ?? null,
            $validated['content'],
        );
        $isTopLevelScrap = ($validated['parent_id'] ?? null) === null;
        $requestedSlug = $isTopLevelScrap
            ? (blank($validated['slug'] ?? null) ? null : $validated['slug'])
            : null;
        $resolvedSlug = $isTopLevelScrap
            ? $this->makeUniqueSlug(
                candidate: $validated['slug'] ?? null,
                fallbackTitle: $resolvedTitle,
                ignoreScrapId: null,
            )
            : null;

        $summary = blank($validated['summary'] ?? null) ? null : $validated['summary'];
        $tags = collect($validated['tags'] ?? [])->filter()->values()->all();

        $scrap = Scrap::create([
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'source_type' => 'note',
            'source_reference' => (string) Str::uuid(),
            'title' => $resolvedTitle,
            'slug' => $resolvedSlug,
            'content' => $validated['content'],
            'content_markdown' => $validated['content'],
            'summary' => $summary,
            'status' => 'raw',
            'occurred_at' => now(),
            'meta' => ['tags' => $tags],
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);

        if ($summary === null) {
            GenerateScrapSummaryJob::dispatch($scrap->id);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->buildSuccessMessage(
                action: 'saved',
                requestedSlug: $requestedSlug,
                resolvedSlug: $resolvedSlug,
            ),
        ]);

        return redirect()->route(
            ...$this->workspaceRedirectTargetForScrap(
                request: $request,
                scrap: $scrap,
                parentScrap: $parentScrap,
            ),
        );
    }

    /**
     * Update the given scrap.
     */
    public function update(UpdateScrapRequest $request, Scrap $scrap): RedirectResponse
    {
        $validated = $request->validated();
        $resolvedTitle = $this->normalizeSuggestedTitle(
            $validated['title'] ?? null,
            $validated['content'],
        );
        $requestedSlug = $scrap->parent_id === null
            ? (blank($validated['slug'] ?? null) ? null : $validated['slug'])
            : null;
        $resolvedSlug = $scrap->parent_id === null
            ? $this->makeUniqueSlug(
                candidate: $validated['slug'] ?? null,
                fallbackTitle: $resolvedTitle,
                ignoreScrapId: $scrap->id,
            )
            : null;

        $summary = blank($validated['summary'] ?? null) ? null : $validated['summary'];
        $tags = collect($validated['tags'] ?? [])->filter()->values()->all();

        $scrap->update([
            'title' => $resolvedTitle,
            'slug' => $resolvedSlug,
            'content' => $validated['content'],
            'content_markdown' => $validated['content'],
            'summary' => $summary,
            'meta' => [
                ...($scrap->meta ?? []),
                'tags' => $tags,
            ],
        ]);

        GenerateScrapEmbeddingJob::dispatch($scrap->id);

        if ($summary === null) {
            GenerateScrapSummaryJob::dispatch($scrap->id);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->buildSuccessMessage(
                action: 'updated',
                requestedSlug: $requestedSlug,
                resolvedSlug: $resolvedSlug,
            ),
        ]);

        return redirect()->route(
            ...$this->workspaceRedirectTargetForScrap($request, $scrap),
        );
    }

    /**
     * Archive multiple top-level scraps (and their children) in one request.
     */
    public function bulkArchive(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', $request->array('ids'))));
        $user = $request->user();

        $scraps = Scrap::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->whereNull('parent_id')
            ->get();

        if ($scraps->isEmpty()) {
            return redirect()->route('scraps');
        }

        $allIdsToArchive = $scraps
            ->flatMap(fn (Scrap $scrap) => $this->collectScrapTreeIds($scrap))
            ->unique()
            ->values()
            ->all();

        Scrap::query()
            ->whereIn('id', $allIdsToArchive)
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count scraps sent to archive.', ['count' => $scraps->count()]),
        ]);

        return redirect()->route('scraps');
    }

    /**
     * Permanently delete multiple archived scraps in one request.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', $request->array('ids'))));
        $user = $request->user();

        $scraps = Scrap::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->whereNull('parent_id')
            ->where('status', 'archived')
            ->get();

        if ($scraps->isEmpty()) {
            return redirect()->route('scraps', ['status' => 'archived']);
        }

        $allIdsToDelete = $scraps
            ->flatMap(fn (Scrap $scrap) => $this->collectScrapTreeIds($scrap))
            ->unique()
            ->values()
            ->all();

        Scrap::query()
            ->whereIn('id', $allIdsToDelete)
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count scraps permanently deleted.', ['count' => $scraps->count()]),
        ]);

        return redirect()->route('scraps', ['status' => 'archived']);
    }

    /**
     * Dispatch an AI job to refine the scrap's content as clean Markdown.
     */
    public function refineMarkdown(Request $request, Scrap $scrap): RedirectResponse
    {
        abort_unless($scrap->user_id === $request->user()->id, 403);

        RefineScrapMarkdownJob::dispatch($scrap->id);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Markdown refinement queued. The scrap will be updated shortly.'),
        ]);

        return redirect()->back();
    }

    /**
     * Archive the given scrap instead of deleting it.
     */
    public function archive(Request $request, Scrap $scrap): RedirectResponse
    {
        abort_unless($scrap->user_id === $request->user()->id, 403);

        $scrapIdsToArchive = $this->collectScrapTreeIds($scrap);

        Scrap::query()
            ->whereIn('id', $scrapIdsToArchive)
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($scrapIdsToArchive) > 1
                ? __('Scrap and its child scraps were sent to archive.')
                : __('Scrap was sent to archive.'),
        ]);

        return redirect()->route(
            ...$this->workspaceRedirectTargetAfterArchive($request, $scrap),
        );
    }

    /**
     * Restore the given archived scrap.
     */
    public function restore(Request $request, Scrap $scrap): RedirectResponse
    {
        abort_unless($scrap->user_id === $request->user()->id, 403);

        $scrapIdsToRestore = $this->collectScrapTreeIds($scrap);
        $ancestorIds = $this->collectAncestorIds($scrap);

        Scrap::query()
            ->whereIn('id', [...$scrapIdsToRestore, ...$ancestorIds])
            ->update([
                'status' => 'raw',
                'updated_at' => now(),
            ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($scrapIdsToRestore) > 1 || $ancestorIds !== []
                ? __('Scrap and related scraps were restored.')
                : __('Scrap was restored.'),
        ]);

        $scrap->refresh();

        return redirect()->route(
            ...$this->workspaceRedirectTargetAfterRestore($request, $scrap),
        );
    }

    /**
     * Permanently delete the given archived scrap.
     */
    public function destroy(Request $request, Scrap $scrap): RedirectResponse
    {
        abort_unless($scrap->user_id === $request->user()->id, 403);

        $scrapIdsToDelete = $this->collectScrapTreeIds($scrap);

        Scrap::query()
            ->whereIn('id', $scrapIdsToDelete)
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => count($scrapIdsToDelete) > 1
                ? __('Scrap and its child scraps were permanently deleted.')
                : __('Scrap was permanently deleted.'),
        ]);

        [$routeName, $parameters] = $this->workspaceRouteContext($request);

        return to_route($routeName, [
            ...$parameters,
            'status' => 'archived',
        ]);
    }

    /**
     * Upload an inline body image and flash its URL back to the current page.
     */
    public function uploadImage(UploadScrapImageRequest $request): RedirectResponse
    {
        $path = $request->file('image')->store(
            'scraps/'.$request->user()->id,
            'public',
        );

        Inertia::flash('scrapImageUpload', [
            'key' => $request->string('upload_key')->toString(),
            'url' => route('images.show', ['path' => $path], false),
        ]);

        return back();
    }

    /**
     * Build the prompt used for metadata suggestions.
     *
     * @param  array<int, string>  $existingSlugs
     */
    private function buildMetadataSuggestionPrompt(
        string $content,
        ?string $currentTitle,
        ?string $currentSlug,
        array $existingSlugs,
        bool $shouldSuggestSlug,
    ): string {
        $slugContext = $shouldSuggestSlug
            ? "Suggest a unique slug candidate that is not in this list:\n- ".implode("\n- ", $existingSlugs ?: ['(none)'])
            : 'This scrap is nested. Return null for the slug.';

        return <<<TEXT
Polish the following scrap metadata.

Current title:
{($currentTitle ?: '(none)')}

Current slug:
{($currentSlug ?: '(none)')}

Current summary:
(none — generate a new one)

Content:
{$content}

{$slugContext}
TEXT;
    }

    /**
     * Normalize the agent's suggested title.
     */
    private function normalizeSuggestedTitle(?string $suggestedTitle, string $content): string
    {
        $normalized = Str::of((string) $suggestedTitle)
            ->trim()
            ->squish()
            ->value();

        if ($normalized !== '') {
            return Str::limit($normalized, self::TITLE_CHARACTER_LIMIT, '');
        }

        $assetTitle = $this->extractStandaloneAssetTitle($content);

        if ($assetTitle !== null) {
            return Str::limit($assetTitle, self::TITLE_CHARACTER_LIMIT, '');
        }

        return Str::limit(
            Str::of($content)->squish()->value(),
            self::TITLE_CHARACTER_LIMIT,
            '',
        );
    }

    /**
     * Extract a compact title when the content is only a single Markdown asset link.
     */
    private function extractStandaloneAssetTitle(string $content): ?string
    {
        $trimmedContent = trim($content);

        if ($trimmedContent === '') {
            return null;
        }

        $matches = [];
        $matched = preg_match(
            '/^!?\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)$/u',
            $trimmedContent,
            $matches,
        );

        if ($matched !== 1) {
            return null;
        }

        $label = trim($matches[1] ?? '');
        $path = trim($matches[2] ?? '');
        $candidate = $label !== '' ? $label : $path;

        $pathFromUrl = parse_url($candidate, PHP_URL_PATH);
        $basename = pathinfo($pathFromUrl ?: $candidate, PATHINFO_FILENAME);

        if ($basename === '') {
            return null;
        }

        return Str::of($basename)
            ->replaceMatches('/[-_]+/u', ' ')
            ->squish()
            ->value();
    }

    /**
     * Normalize and uniquify a slug candidate.
     */
    private function makeUniqueSlug(?string $candidate, string $fallbackTitle, ?int $ignoreScrapId): string
    {
        $baseSlug = Str::slug($candidate ?: $fallbackTitle);

        if ($baseSlug === '') {
            $baseSlug = 'untitled-scrap';
        }

        $baseSlug = Str::limit($baseSlug, 80, '');
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreScrapId)) {
            $suffix = '-'.$counter;
            $slug = Str::limit($baseSlug, 80 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $slug;
    }

    /**
     * Collect the selected scrap id and all descendant scrap ids.
     *
     * @return array<int, int>
     */
    private function collectScrapTreeIds(Scrap $scrap): array
    {
        $queuedIds = [$scrap->id];
        $collectedIds = [];

        while ($queuedIds !== []) {
            $currentIds = $queuedIds;
            $queuedIds = [];
            $collectedIds = [...$collectedIds, ...$currentIds];

            $queuedIds = Scrap::query()
                ->whereIn('parent_id', $currentIds)
                ->pluck('id')
                ->all();
        }

        return array_values(array_unique($collectedIds));
    }

    /**
     * Collect all ancestor scrap ids up to the top-level parent.
     *
     * @return array<int, int>
     */
    private function collectAncestorIds(Scrap $scrap): array
    {
        $ancestorIds = [];
        $parentId = $scrap->parent_id;

        while ($parentId !== null) {
            $ancestorIds[] = $parentId;
            $parentId = Scrap::query()
                ->whereKey($parentId)
                ->value('parent_id');
        }

        return array_values(array_unique($ancestorIds));
    }

    /**
     * Determine whether a slug already exists.
     */
    private function slugExists(string $slug, ?int $ignoreScrapId): bool
    {
        return Scrap::query()
            ->where('slug', $slug)
            ->when(
                $ignoreScrapId !== null,
                fn ($query) => $query->whereKeyNot($ignoreScrapId),
            )
            ->exists();
    }

    /**
     * Build a success message for scrap persistence.
     */
    private function buildSuccessMessage(
        string $action,
        ?string $requestedSlug,
        ?string $resolvedSlug,
    ): string {
        $message = $action === 'updated' ? __('Scrap updated.') : __('Scrap saved.');

        if (
            $requestedSlug !== null
            && $resolvedSlug !== null
            && $requestedSlug !== $resolvedSlug
        ) {
            return $message.' '.__('Slug adjusted to :slug.', ['slug' => $resolvedSlug]);
        }

        return $message;
    }

    /**
     * Resolve the dashboard route name and parameters after storing or updating a scrap.
     *
     * @return array{0: string, 1?: array<string, string>}
     */
    private function workspaceRedirectTargetForScrap(
        Request $request,
        Scrap $scrap,
        ?Scrap $parentScrap = null,
    ): array {
        [$indexRoute, $query] = $this->workspaceRouteContext($request);
        $showRoute = $indexRoute === 'scraps' ? 'scraps.show' : 'dashboard.show';

        if ($scrap->parent_id !== null) {
            $parentScrap ??= $scrap->parent;

            if ($parentScrap?->slug !== null) {
                return [$showRoute, [...$query, 'slug' => $parentScrap->slug]];
            }
        }

        if ($scrap->slug !== null) {
            return [$showRoute, [...$query, 'slug' => $scrap->slug]];
        }

        return [$indexRoute, $query];
    }

    /**
     * Resolve the dashboard route name and parameters after archiving a scrap.
     *
     * @return array{0: string, 1?: array<string, string>}
     */
    private function workspaceRedirectTargetAfterArchive(Request $request, Scrap $scrap): array
    {
        [$indexRoute, $query] = $this->workspaceRouteContext($request);
        $showRoute = $indexRoute === 'scraps' ? 'scraps.show' : 'dashboard.show';

        if ($scrap->parent_id !== null && $scrap->parent?->slug !== null) {
            return [$showRoute, [...$query, 'slug' => $scrap->parent->slug]];
        }

        return [$indexRoute, $query];
    }

    /**
     * Resolve the dashboard route after restoring a scrap.
     *
     * @return array{0: string, 1?: array<string, string>}
     */
    private function workspaceRedirectTargetAfterRestore(Request $request, Scrap $scrap): array
    {
        [$indexRoute, $query] = $this->workspaceRouteContext($request);
        $showRoute = $indexRoute === 'scraps' ? 'scraps.show' : 'dashboard.show';

        if (($query['status'] ?? null) === 'archived') {
            unset($query['status']);
        }

        if ($scrap->parent_id !== null && $scrap->parent?->slug !== null) {
            return [$showRoute, [...$query, 'slug' => $scrap->parent->slug]];
        }

        if ($scrap->slug !== null) {
            return [$showRoute, [...$query, 'slug' => $scrap->slug]];
        }

        return [$indexRoute, $query];
    }

    /**
     * Resolve the current workspace route names and supported query parameters.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function workspaceRouteContext(Request $request): array
    {
        $referer = (string) $request->headers->get('referer', '');
        $path = parse_url($referer, PHP_URL_PATH) ?: '';
        $rawQuery = parse_url($referer, PHP_URL_QUERY) ?: '';
        $parsedQuery = [];

        parse_str($rawQuery, $parsedQuery);

        $query = array_filter([
            'tag' => is_string($parsedQuery['tag'] ?? null) && $parsedQuery['tag'] !== ''
                ? $parsedQuery['tag']
                : null,
            'status' => ($parsedQuery['status'] ?? null) === 'archived'
                ? 'archived'
                : null,
        ]);

        if (str_starts_with($path, '/scraps')) {
            return ['scraps', $query];
        }

        return ['dashboard', $query];
    }
}
