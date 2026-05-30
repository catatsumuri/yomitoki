<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use App\Models\ScrapRevision;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScrapRevisionsController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $scrap = Scrap::query()
            ->where('user_id', $request->user()->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $snapshots = $scrap->revisions()
            ->select(['id', 'content', 'created_at'])
            ->get()
            ->map(fn (ScrapRevision $revision) => [
                'id' => $revision->id,
                'content' => $revision->content,
                'createdAt' => $revision->created_at->toIso8601String(),
                'isCurrent' => false,
            ])
            ->all();

        $current = [
            'id' => 0,
            'content' => $scrap->content,
            'createdAt' => $scrap->updated_at->toIso8601String(),
            'isCurrent' => true,
        ];

        return Inertia::render('dashboard/revisions', [
            'scrap' => [
                'slug' => $scrap->slug,
                'title' => $scrap->title,
            ],
            'revisions' => [$current, ...$snapshots],
        ]);
    }
}
