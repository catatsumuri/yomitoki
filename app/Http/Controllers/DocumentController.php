<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ComposeDocumentAgent;
use App\Jobs\ComposeDocumentJob;
use App\Models\AiRun;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * List documents for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $documents = Inertia::scroll(fn () => Document::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10, pageName: 'documents')
            ->through(fn (Document $document) => $this->mapDocument($document)));

        return Inertia::render('documents', [
            'documents' => $documents,
            'selectedDocument' => null,
        ]);
    }

    /**
     * Show a single document.
     */
    public function show(Request $request, Document $document): Response
    {
        abort_if($document->user_id !== $request->user()->id, 403);

        $document->load('scraps');

        $documents = Inertia::scroll(fn () => Document::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10, pageName: 'documents')
            ->through(fn (Document $doc) => $this->mapDocument($doc)));

        return Inertia::render('documents', [
            'documents' => $documents,
            'selectedDocument' => $this->mapDocument($document, withScraps: true),
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function mapDocument(Document $document, bool $withScraps = false): array
    {
        $data = [
            'id' => $document->id,
            'title' => $document->title,
            'documentType' => $document->document_type,
            'status' => $document->status,
            'summary' => $document->summary,
            'outline' => $document->outline,
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
