<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ComposeDocumentAgent;
use App\Jobs\ComposeDocumentJob;
use App\Models\AiRun;
use App\Models\Document;
use App\Models\DocumentRevision;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DocumentController extends Controller
{
    /**
     * List documents for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $tag = $request->string('tag')->trim()->value();
        $baseQuery = Document::query()->where('user_id', $request->user()->id);

        $documents = Inertia::scroll(fn () => $this->applyTagFilter(clone $baseQuery, $tag)
            ->latest()
            ->paginate(10, pageName: 'documents')
            ->through(fn (Document $document) => $this->mapDocument($document)));

        return Inertia::render('documents', [
            'documents' => $documents,
            'selectedDocument' => null,
            'availableTags' => $this->availableTagsForUser($request->user()->id),
            'activeTag' => $tag !== '' ? $tag : null,
        ]);
    }

    /**
     * Show a single document.
     */
    public function show(Request $request, Document $document): Response
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $tag = $request->string('tag')->trim()->value();
        $document->load('scraps');

        $baseQuery = Document::query()->where('user_id', $request->user()->id);

        $documents = Inertia::scroll(fn () => $this->applyTagFilter(clone $baseQuery, $tag)
            ->latest()
            ->paginate(10, pageName: 'documents')
            ->through(fn (Document $doc) => $this->mapDocument($doc)));

        return Inertia::render('documents', [
            'documents' => $documents,
            'selectedDocument' => $this->mapDocument($document, withScraps: true),
            'availableTags' => $this->availableTagsForUser($request->user()->id),
            'activeTag' => $tag !== '' ? $tag : null,
        ]);
    }

    /**
     * Show the edit form for a document.
     */
    public function edit(Request $request, Document $document): Response
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $meta = is_array($document->meta) ? $document->meta : [];

        return Inertia::render('documents/edit', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'contentMarkdown' => $document->content_markdown,
                'tags' => collect($meta['tags'] ?? [])
                    ->filter(fn (mixed $tag) => is_string($tag) && $tag !== '')
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Show the revision history for a document.
     */
    public function revisions(Request $request, Document $document): Response
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $snapshots = $document->revisions()
            ->select(['id', 'title', 'content_markdown', 'created_at'])
            ->get()
            ->map(fn (DocumentRevision $revision) => [
                'id' => $revision->id,
                'title' => $revision->title,
                'contentMarkdown' => $revision->content_markdown,
                'createdAt' => $revision->created_at->toIso8601String(),
                'isCurrent' => false,
            ])
            ->all();

        $current = [
            'id' => 0,
            'title' => $document->title,
            'contentMarkdown' => $document->content_markdown,
            'createdAt' => $document->updated_at->toIso8601String(),
            'isCurrent' => true,
        ];

        return Inertia::render('documents/revisions', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
            ],
            'revisions' => [$current, ...$snapshots],
        ]);
    }

    /**
     * Update a document, saving the current state as a revision first.
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content_markdown' => ['required', 'string'],
            'tags' => ['present', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        DocumentRevision::create([
            'document_id' => $document->id,
            'title' => $document->title,
            'content_markdown' => $document->content_markdown,
        ]);

        $tags = array_values(array_unique(array_filter(
            $validated['tags'],
            fn (mixed $tag) => is_string($tag) && $tag !== '',
        )));

        $document->update([
            'title' => $validated['title'],
            'content_markdown' => $validated['content_markdown'],
            'meta' => [
                ...($document->meta ?? []),
                'tags' => $tags,
            ],
        ]);

        return redirect()->route('documents.show', $document);
    }

    /**
     * Stream the document as a PDF download.
     */
    public function pdf(Request $request, Document $document): SymfonyResponse
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $meta = is_array($document->meta) ? $document->meta : [];
        $tags = collect($meta['tags'] ?? [])
            ->filter(fn (mixed $tag) => is_string($tag) && $tag !== '')
            ->values()
            ->all();

        $contentHtml = $document->content_markdown
            ? Str::markdown($document->content_markdown)
            : '';

        $fontCache = storage_path('fonts');

        if (! is_dir($fontCache) && ! mkdir($fontCache, 0755, true) && ! is_dir($fontCache)) {
            throw new RuntimeException('Unable to create PDF font cache directory.');
        }

        $pdf = Pdf::setOptions([
            'fontDir' => $fontCache,
            'fontCache' => $fontCache,
            'chroot' => base_path(),
            'defaultFont' => 'IPAGothic',
        ])->loadView('documents.pdf', [
            'title' => $document->title,
            'documentType' => $document->document_type,
            'tags' => $tags,
            'summary' => $document->summary,
            'contentHtml' => $contentHtml,
            'createdAt' => $document->created_at,
        ]);

        return $pdf->download(Str::slug($document->title).'.pdf');
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, Document $document): RedirectResponse
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $document->delete();

        return redirect()->route('documents');
    }

    /**
     * Dispatch a job to compose a document from the given scraps.
     */
    public function compose(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'document_type' => 'nullable|string|in:spec,summary,report',
        ]);

        $documentType = $request->input('document_type', 'spec');

        $aiRun = AiRun::create([
            'user_id' => $request->user()->id,
            'run_type' => 'compose_document',
            'agent_name' => ComposeDocumentAgent::class,
            'status' => 'queued',
            'input_payload' => [
                'scrap_ids' => $request->input('ids'),
                'document_type' => $documentType,
            ],
        ]);

        ComposeDocumentJob::dispatch(
            $request->user()->id,
            $request->input('ids'),
            $documentType,
            $aiRun->id,
        );

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Composing document…'),
        ]);

        return redirect()->back();
    }

    private function applyTagFilter(EloquentBuilder $query, string $tag): EloquentBuilder
    {
        if ($tag === '') {
            return $query;
        }

        return $query->whereJsonContains('meta->tags', $tag);
    }

    /**
     * @return list<string>
     */
    private function availableTagsForUser(int $userId): array
    {
        return Document::query()
            ->where('user_id', $userId)
            ->whereNotNull('meta')
            ->pluck('meta')
            ->flatMap(function (mixed $meta): array {
                $tags = $meta['tags'] ?? [];

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

    /**
     * @return array<string, mixed>
     */
    private function mapDocument(Document $document, bool $withScraps = false): array
    {
        $meta = is_array($document->meta) ? $document->meta : [];

        $data = [
            'id' => $document->id,
            'title' => $document->title,
            'documentType' => $document->document_type,
            'tags' => collect($meta['tags'] ?? [])
                ->filter(fn (mixed $tag) => is_string($tag) && $tag !== '')
                ->values()
                ->all(),
            'summary' => $document->summary,
            'contentMarkdown' => $withScraps ? $document->content_markdown : null,
            'createdAt' => $document->created_at->toIso8601String(),
        ];

        if ($withScraps) {
            $data['scraps'] = $document->scraps->map(fn ($scrap) => [
                'id' => $scrap->id,
                'title' => $scrap->title,
                'summary' => $scrap->summary,
                'sourceType' => $scrap->source_type,
                'slug' => $scrap->slug,
            ])->values()->all();
        }

        return $data;
    }
}
