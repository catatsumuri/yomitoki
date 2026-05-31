<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access api tokens page', function () {
    $this->get('/config/api-tokens')->assertRedirect('/login');
});

test('authenticated user can view api tokens page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/config/api-tokens')
        ->assertOk();
});

test('user can create an api token', function () {
    $user = User::factory()->create();

    $token = $this->actingAs($user)
        ->post('/config/api-tokens', ['name' => 'my-token']);

    $pat = PersonalAccessToken::where('tokenable_id', $user->id)->firstOrFail();

    $token->assertRedirect(route('api-tokens.show', $pat->id));
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

test('user can view a single api token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('view-token', ['ingest'])->accessToken;

    $this->actingAs($user)
        ->get("/config/api-tokens/{$token->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('config/api-tokens/show')
            ->where('token.id', $token->id)
            ->where('token.name', 'view-token')
        );
});

test('user can regenerate an api token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('regenerate-me', ['ingest'], now()->addDays(30))->accessToken;

    $response = $this->actingAs($user)
        ->post("/config/api-tokens/{$token->id}/regenerate");

    $response->assertRedirect();
    expect(PersonalAccessToken::find($token->id))->toBeNull();
    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(1);
});

test('created token has ingest ability', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/config/api-tokens', ['name' => 'my-token']);

    $token = PersonalAccessToken::where('tokenable_id', $user->id)->first();

    expect($token->can('ingest'))->toBeTrue();
});

test('token with expiry is saved with expires_at', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/config/api-tokens', ['name' => 'short-lived', 'expires_in_days' => 30]);

    $token = PersonalAccessToken::where('tokenable_id', $user->id)->first();

    expect($token->expires_at)->not->toBeNull();
});

test('user can revoke their own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('to-revoke', ['ingest']);

    $this->actingAs($user)
        ->delete("/config/api-tokens/{$token->accessToken->id}")
        ->assertRedirect('/config/api-tokens');

    expect(PersonalAccessToken::find($token->accessToken->id))->toBeNull();
});

test('user can bulk revoke their own tokens', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $tokenA = $user->createToken('bulk-a', ['ingest'])->accessToken;
    $tokenB = $user->createToken('bulk-b', ['ingest'])->accessToken;
    $otherToken = $other->createToken('other-token', ['ingest'])->accessToken;

    $this->actingAs($user)
        ->delete('/config/api-tokens', [
            'token_ids' => [$tokenA->id, $tokenB->id, $otherToken->id],
        ])
        ->assertRedirect('/config/api-tokens');

    expect(PersonalAccessToken::find($tokenA->id))->toBeNull();
    expect(PersonalAccessToken::find($tokenB->id))->toBeNull();
    expect(PersonalAccessToken::find($otherToken->id))->not->toBeNull();
});

test('user cannot revoke another users token', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken('other-token', ['ingest']);

    $this->actingAs($user)
        ->delete("/config/api-tokens/{$token->accessToken->id}")
        ->assertNotFound();
});
