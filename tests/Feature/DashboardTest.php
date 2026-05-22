<?php

use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', config('app.name'))
            ->where('summary.inboxCount', 3)
            ->where('summary.attentionCount', 3)
            ->where('summary.documentCount', 4)
            ->where('summary.runningAiCount', 1)
            ->where('selectedScrap', null)
            ->has('inboxItems.data', 5)
            ->where('inboxItems.data.0.title', '日報: 仕様書ドラフト画面の準備')
            ->has('needsAttention', 3)
            ->has('recentDocuments', 4)
            ->has('sourceOverview', 4)
            ->has('recentAiRuns', 5)
            ->where('inboxItems.data', fn ($items): bool => collect($items)
                ->pluck('title')
                ->doesntContain('調査: 旧 dashboard 指標案')
                && collect($items)->pluck('title')->doesntContain('問い合わせ: 要件の抜け漏れが起きやすい'))
        );
});

test('authenticated users can visit a scrap detail page by slug', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($user);

    $response = $this->get(route('dashboard.show', ['slug' => 'spec-draft-takes-too-long']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedScrap.title', '問い合わせ: 仕様書の初稿に時間がかかる')
            ->where('selectedScrap.slug', 'spec-draft-takes-too-long')
            ->has('selectedScrap.children', 1)
            ->where('selectedScrap.children.0.title', '問い合わせ: 要件の抜け漏れが起きやすい')
        );
});
