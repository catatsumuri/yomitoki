<?php

namespace App\Http\Controllers;

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchivesController extends Controller
{
    /**
     * Show archived scraps for the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('archives', [
            'summary' => [
                'archivedCount' => Scrap::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'archived')
                    ->count(),
                'topLevelCount' => Scrap::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'archived')
                    ->whereNull('parent_id')
                    ->count(),
                'nestedCount' => Scrap::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'archived')
                    ->whereNotNull('parent_id')
                    ->count(),
            ],
            'archivedItems' => Inertia::scroll(fn () => Scrap::query()
                ->where('user_id', $user->id)
                ->where('status', 'archived')
                ->with('parent:id,title,slug')
                ->latest('updated_at')
                ->paginate(10, pageName: 'archived')
                ->through(fn (Scrap $scrap) => $this->mapArchivedScrap($scrap))),
        ]);
    }

    /**
     * Transform an archived scrap for frontend rendering.
     *
     * @return array<string, mixed>
     */
    private function mapArchivedScrap(Scrap $scrap): array
    {
        return [
            'id' => $scrap->id,
            'title' => $scrap->title,
            'slug' => $scrap->slug,
            'content' => $scrap->content,
            'summary' => $scrap->summary,
            'sourceType' => $scrap->source_type,
            'isNested' => $scrap->parent_id !== null,
            'parent' => $scrap->parent ? [
                'id' => $scrap->parent->id,
                'title' => $scrap->parent->title,
                'slug' => $scrap->parent->slug,
            ] : null,
            'occurredAt' => $scrap->occurred_at?->toIso8601String(),
            'archivedAt' => $scrap->updated_at?->toIso8601String(),
        ];
    }
}
