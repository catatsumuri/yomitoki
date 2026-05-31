<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * Show the API tokens settings page.
     */
    public function edit(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ]);

        return Inertia::render('config/api-tokens', [
            'tokens' => $tokens,
        ]);
    }

    /**
     * Show the detail page for a single token.
     */
    public function show(Request $request, int $tokenId): Response
    {
        $token = $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->firstOrFail();

        return Inertia::render('config/api-tokens/show', [
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Regenerate the given token (revoke + issue new one with same name/expiry).
     */
    public function regenerate(Request $request, int $tokenId): RedirectResponse
    {
        $old = $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->firstOrFail();

        $expiresAt = $old->expires_at;
        $name = $old->name;

        $old->delete();

        $token = $request->user()->createToken($name, ['ingest'], $expiresAt);

        Inertia::flash('newToken', $token->plainTextToken);

        return to_route('api-tokens.show', $token->accessToken->id);
    }

    /**
     * Create a new API token.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', Rule::in([30, 90, 365])],
        ]);

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays((int) $validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken(
            $validated['name'],
            ['ingest'],
            $expiresAt,
        );

        Inertia::flash('newToken', $token->plainTextToken);

        return to_route('api-tokens.show', $token->accessToken->id);
    }

    /**
     * Revoke the given API token.
     */
    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $deleted = $request->user()
            ->tokens()
            ->where('id', $tokenId)
            ->delete();

        abort_if($deleted === 0, 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return to_route('api-tokens.edit');
    }

    /**
     * Revoke multiple tokens at once.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token_ids' => ['required', 'array'],
            'token_ids.*' => ['integer'],
        ]);

        $request->user()
            ->tokens()
            ->whereIn('id', $validated['token_ids'])
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tokens revoked.')]);

        return to_route('api-tokens.edit');
    }
}
