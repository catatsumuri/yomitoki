<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ComposeDocumentAgent;
use App\Jobs\ComposeDocumentJob;
use App\Models\AiRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentController extends Controller
{
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
}
