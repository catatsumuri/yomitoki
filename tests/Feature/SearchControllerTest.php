<?php

use App\Ai\Agents\SearchScrapAgent;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

test('index creates a new conversation and renders search page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('search'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('search')
            ->has('conversationId'),
    );

    $conversationId = $response->baseResponse->original->getData()['page']['props']['conversationId'];

    expect(DB::table('agent_conversations')->where('id', $conversationId)->exists())->toBeTrue();
});

test('index requires authentication', function () {
    $this->get(route('search'))->assertRedirect(route('login'));
});

test('query streams a response for a valid request', function () {
    SearchScrapAgent::fake(['関連するスクラップを見つけました。']);

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'テスト',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('search.query'), [
        'query' => 'embedding の実装どうしてた？',
        'conversation_id' => $conversationId,
    ]);

    $response->assertOk();

    SearchScrapAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'embedding'));
});

test('query validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('search.query'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query', 'conversation_id']);
});

test('init creates a new conversation and returns its id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('search.init'));

    $response->assertOk()->assertJsonStructure(['conversation_id']);

    $conversationId = $response->json('conversation_id');

    expect(Str::isUuid($conversationId))->toBeTrue();
    expect(DB::table('agent_conversations')->where('id', $conversationId)->exists())->toBeTrue();
});

test('init requires authentication', function () {
    $this->postJson(route('search.init'))->assertUnauthorized();
});

test('query injects related scraps into prompt when embeddings exist', function () {
    SearchScrapAgent::fake(['shiki を使ってシンタックスハイライトを実装しました。']);

    // Use the same vector for both the query embedding and the stored scrap embedding
    // so pgvector returns max similarity (cosine distance = 0)
    $sharedEmbedding = array_fill(0, 1024, 0.1);

    Embeddings::fake([[$sharedEmbedding]]);

    $user = User::factory()->create();
    $conversationId = (string) Str::uuid();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => $user->id,
        'title' => 'テスト',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Scrap::factory()->create([
        'user_id' => $user->id,
        'title' => 'シンタックスハイライト実装',
        'summary' => 'shiki を使ったコードハイライトの実装',
        'embedding' => json_encode($sharedEmbedding),
        'status' => 'raw',
    ]);

    $this->actingAs($user)->post(route('search.query'), [
        'query' => 'シンタックスハイライトはどうしてた？',
        'conversation_id' => $conversationId,
    ])->assertOk();

    SearchScrapAgent::assertPrompted(
        fn ($prompt) => str_contains($prompt->prompt, '関連スクラップ'),
    );
});
