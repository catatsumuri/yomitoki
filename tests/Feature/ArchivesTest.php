<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('archives'))
        ->assertRedirect(route('login'));
});

test('authenticated users are redirected from archives to the dashboard archived filter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('archives'));

    $response->assertRedirect(route('dashboard', ['status' => 'archived']));
});
