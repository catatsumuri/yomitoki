<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

test('job generates and stores embedding for a scrap', function () {
    Embeddings::fake([
        [Embeddings::fakeEmbedding(1536)],
    ]);

    $user = User::factory()->create();
    $scrap = Scrap::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test scrap',
        'content' => 'Some content here.',
    ]);

    GenerateScrapEmbeddingJob::dispatchSync($scrap->id);

    $scrap->refresh();

    expect($scrap->embedding)->not->toBeNull();
    expect($scrap->embedding_generated_at)->not->toBeNull();
    expect(json_decode($scrap->embedding, true))->toHaveCount(1536);
});

test('job does nothing when scrap does not exist', function () {
    Embeddings::fake([]);

    expect(fn () => GenerateScrapEmbeddingJob::dispatchSync(999999))->not->toThrow(Throwable::class);

    Embeddings::assertNothingGenerated();
});

test('job uses summary instead of content when summary is present', function () {
    Embeddings::fake([
        [Embeddings::fakeEmbedding(1536)],
    ]);

    $user = User::factory()->create();
    $scrap = Scrap::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test scrap',
        'summary' => 'AI が生成した日本語の要約です。',
        'content' => "説明文\n\n```ts\nconst x = 1;\n```\n\nさらに説明",
    ]);

    GenerateScrapEmbeddingJob::dispatchSync($scrap->id);

    $scrap->refresh();

    expect($scrap->embedding)->not->toBeNull();
    expect(json_decode($scrap->embedding, true))->toHaveCount(1536);
});

test('job strips code blocks from content when no summary', function () {
    Embeddings::fake([
        [Embeddings::fakeEmbedding(1536)],
    ]);

    $user = User::factory()->create();
    $scrap = Scrap::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test scrap',
        'summary' => null,
        'content' => "説明文\n\n```ts\nconst x = 1;\n```\n\nさらに説明",
    ]);

    GenerateScrapEmbeddingJob::dispatchSync($scrap->id);

    $scrap->refresh();

    expect($scrap->embedding)->not->toBeNull();
    expect(json_decode($scrap->embedding, true))->toHaveCount(1536);
});

test('job uses content directly when no summary and no code blocks', function () {
    Embeddings::fake([
        [Embeddings::fakeEmbedding(1536)],
    ]);

    $user = User::factory()->create();
    $scrap = Scrap::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test scrap',
        'summary' => null,
        'content' => '純粋な日本語のメモです。コードブロックは含まれていません。',
    ]);

    GenerateScrapEmbeddingJob::dispatchSync($scrap->id);

    $scrap->refresh();

    expect($scrap->embedding)->not->toBeNull();
    expect(json_decode($scrap->embedding, true))->toHaveCount(1536);
});

test('store scrap dispatches embedding job', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scraps.store'), [
        'title' => 'Embedding dispatch test',
        'content' => 'Content for embedding.',
        'organize' => false,
    ]);

    $scrap = Scrap::query()->first();

    expect($scrap)->not->toBeNull();
});
