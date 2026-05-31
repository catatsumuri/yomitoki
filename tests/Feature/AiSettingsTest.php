<?php

use App\Ai\Agents\PingAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access ai settings page', function () {
    $this->get('/config/ai')->assertRedirect('/login');
});

test('authenticated user can view ai settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/config/ai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('config/ai')
            ->where('provider', 'azure')
        );
});

test('ping endpoint returns text/event-stream', function () {
    PingAgent::fake(['pong']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/config/ai/ping');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
});
