<?php

use App\Ai\Agents\SuggestScrapMetadataAgent;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;

uses(RefreshDatabase::class);

test('authenticated users can request metadata suggestions for top level scraps', function () {
    $user = User::factory()->create();

    SuggestScrapMetadataAgent::fake([
        [
            'title' => 'Polished dashboard direction',
            'slug' => 'dashboard-direction',
        ],
    ])->preventStrayPrompts();

    $response = $this
        ->actingAs($user)
        ->postJson(route('scraps.suggest-metadata'), [
            'content' => 'Inbox を最上段に置き、未整理 scrap から始められるようにする。',
            'title' => '',
            'slug' => '',
        ]);

    $response->assertOk()
        ->assertJson([
            'title' => 'Polished dashboard direction',
            'slug' => 'dashboard-direction',
        ]);

    SuggestScrapMetadataAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->contains('Suggest a unique slug candidate')
            && $prompt->contains('Inbox を最上段');
    });
});

test('suggested slugs are uniquified before being returned', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'slug' => 'dashboard-direction',
    ]);

    SuggestScrapMetadataAgent::fake([
        [
            'title' => 'Polished dashboard direction',
            'slug' => 'dashboard-direction',
        ],
    ])->preventStrayPrompts();

    $response = $this
        ->actingAs($user)
        ->postJson(route('scraps.suggest-metadata'), [
            'content' => 'Dashboard の入口は Inbox 優先。',
        ]);

    $response->assertOk()
        ->assertJson([
            'slug' => 'dashboard-direction-2',
        ]);
});

test('nested scraps receive a title suggestion but no slug suggestion', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create();

    SuggestScrapMetadataAgent::fake([
        [
            'title' => 'Follow-up detail',
            'slug' => null,
        ],
    ])->preventStrayPrompts();

    $response = $this
        ->actingAs($user)
        ->postJson(route('scraps.suggest-metadata'), [
            'parent_id' => $parentScrap->id,
            'content' => '親記事の下に続きの論点を追加したい。',
        ]);

    $response->assertOk()
        ->assertJson([
            'title' => 'Follow-up detail',
            'slug' => null,
        ]);

    SuggestScrapMetadataAgent::assertPrompted(function (AgentPrompt $prompt) {
        return $prompt->contains('This scrap is nested. Return null for the slug.');
    });
});
