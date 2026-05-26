<?php

use App\Models\Scrap;
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
            ->where('activeStatus', 'active')
            ->where('summary.inboxCount', 3)
            ->where('summary.attentionCount', 3)
            ->where('summary.documentCount', 4)
            ->where('summary.runningAiCount', 1)
            ->where('selectedScrap', null)
            ->where('activeTag', null)
            ->where('availableTags', ['dev', 'research', 'spec', 'support', 'yomitoki'])
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

test('authenticated users can filter the dashboard by tag', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($user);

    $response = $this->get(route('dashboard', ['tag' => 'support']));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTag', 'support')
            ->where('activeStatus', 'active')
            ->where('summary.inboxCount', 1)
            ->where('summary.attentionCount', 1)
            ->has('inboxItems.data', 2)
            ->where('inboxItems.data', fn ($items): bool => collect($items)
                ->pluck('title')
                ->contains('問い合わせ: 仕様書の初稿に時間がかかる')
                && collect($items)->pluck('title')->contains('問い合わせ: 仕様変更の背景も残したい')
                && collect($items)->pluck('title')->doesntContain('日報: 仕様書ドラフト画面の準備'))
            ->where('inboxItems.data.0.tags', ['yomitoki', 'support'])
            ->has('needsAttention', 1)
        );
});

test('authenticated users can visit a scrap detail page by slug', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($user);

    $response = $this->get(route('dashboard.show', ['slug' => 'spec-draft-takes-too-long']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeStatus', 'active')
            ->where('selectedScrap.title', '問い合わせ: 仕様書の初稿に時間がかかる')
            ->where('selectedScrap.slug', 'spec-draft-takes-too-long')
            ->where('selectedScrap.tags', ['yomitoki', 'support'])
            ->has('selectedScrap.children', 1)
            ->where('selectedScrap.children.0.title', '問い合わせ: 要件の抜け漏れが起きやすい')
        );
});

test('authenticated users can view the localized syntax highlighting scrap', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'source_type' => 'plan',
        'status' => 'processed',
        'title' => 'Markdown コードブロックのシンタックスハイライト',
        'slug' => 'syntax-highlighting',
        'summary' => 'このプランでは、`MarkdownPreview` コンポーネントの Markdown コードブロックに `shiki` を使ったシンタックスハイライトを追加します。',
        'content' => "# Markdown コードブロックのシンタックスハイライト\n\n## 背景\n\n`shiki` を使ってシンタックスハイライトを追加します。",
        'content_markdown' => "# Markdown コードブロックのシンタックスハイライト\n\n## 背景\n\n`shiki` を使ってシンタックスハイライトを追加します。",
        'language' => 'ja',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard.show', ['slug' => 'syntax-highlighting']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeStatus', 'active')
            ->where('selectedScrap.title', 'Markdown コードブロックのシンタックスハイライト')
            ->where('selectedScrap.slug', 'syntax-highlighting')
            ->where('selectedScrap.summary', 'このプランでは、`MarkdownPreview` コンポーネントの Markdown コードブロックに `shiki` を使ったシンタックスハイライトを追加します。')
            ->where('selectedScrap.content', "# Markdown コードブロックのシンタックスハイライト\n\n## 背景\n\n`shiki` を使ってシンタックスハイライトを追加します。")
        );
});

test('authenticated users can filter the dashboard to archived top level scraps', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    Scrap::factory()->for($user)->create([
        'title' => 'アーカイブ済みトップレベル',
        'slug' => 'archived-top-level',
        'status' => 'archived',
    ]);

    Scrap::factory()->for($user)->create([
        'title' => 'アーカイブ済み子スクラップ',
        'slug' => null,
        'status' => 'archived',
        'parent_id' => Scrap::query()
            ->where('user_id', $user->id)
            ->where('slug', 'spec-generation-flow')
            ->value('id'),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', ['status' => 'archived']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeStatus', 'archived')
            ->where('summary.inboxCount', 2)
            ->where('summary.attentionCount', 0)
            ->has('inboxItems.data', 2)
            ->where('inboxItems.data', fn ($items): bool => collect($items)
                ->pluck('slug')
                ->contains('archived-top-level')
                && collect($items)->pluck('slug')->contains('legacy-dashboard-metrics')
                && collect($items)->pluck('title')->doesntContain('アーカイブ済み子スクラップ'))
        );
});

test('authenticated users can view an archived scrap detail page by slug', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'source_type' => 'plan',
        'status' => 'archived',
        'title' => 'アーカイブ済みプラン',
        'slug' => 'archived-plan',
        'summary' => 'アーカイブ済みの要約です。',
        'content' => 'archived body',
        'content_markdown' => 'archived body',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard.show', [
        'slug' => 'archived-plan',
        'status' => 'archived',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeStatus', 'archived')
            ->where('selectedScrap.title', 'アーカイブ済みプラン')
            ->where('selectedScrap.slug', 'archived-plan')
            ->where('selectedScrap.status', 'archived')
        );
});
