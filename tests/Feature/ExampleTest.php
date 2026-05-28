<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('welcome page passes canResetPassword prop', function () {
    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->has('canResetPassword')
    );
});

test('welcome page passes session status prop', function () {
    $response = $this->withSession(['status' => 'Password reset successful.'])
        ->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('welcome')
        ->where('status', 'Password reset successful.')
    );
});

test('welcome page is accessible when authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
});
