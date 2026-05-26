<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArchivesController extends Controller
{
    /**
     * Redirect the legacy archives page to the dashboard status filter.
     */
    public function index(Request $request): RedirectResponse
    {
        $tag = $request->string('tag')->trim()->value();

        return to_route('dashboard', array_filter([
            'status' => 'archived',
            'tag' => $tag !== '' ? $tag : null,
        ]));
    }
}
