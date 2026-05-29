<?php

use App\Models\Scrap;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page for scraps', function () {
    $this->get(route('scraps'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the scraps page', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $response = $this->actingAs($user)->get(route('scraps'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scraps')
            ->where('view', 'list')
            ->where('activeStatus', 'active')
            ->has('scraps.data', 5)
            ->where('selectedScrap', null)
        );
});

test('authenticated users can filter articles to archived scraps', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $response = $this->actingAs($user)->get(route('scraps', ['status' => 'archived']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scraps')
            ->where('activeStatus', 'archived')
            ->has('scraps.data', 1)
            ->where('scraps.data.0.slug', 'legacy-dashboard-metrics')
        );
});

test('authenticated users can open a scrap detail page by slug', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $response = $this->actingAs($user)->get(route('scraps.show', ['slug' => 'spec-draft-takes-too-long']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scraps')
            ->where('activeStatus', 'active')
            ->where('selectedScrap.slug', 'spec-draft-takes-too-long')
        );
});

test('authenticated users can open an archived article detail page by slug', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'source_type' => 'plan',
        'status' => 'archived',
        'title' => 'アーカイブ済み記事',
        'slug' => 'archived-article',
        'content' => 'archived body',
        'content_markdown' => 'archived body',
    ]);

    $response = $this->actingAs($user)->get(route('scraps.show', [
        'slug' => 'archived-article',
        'status' => 'archived',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scraps')
            ->where('activeStatus', 'archived')
            ->where('selectedScrap.slug', 'archived-article')
        );
});

test('authenticated users can view the backup history page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('scraps', ['view' => 'backups']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('scraps')
            ->where('view', 'backups')
            ->has('backups')
        );
});
