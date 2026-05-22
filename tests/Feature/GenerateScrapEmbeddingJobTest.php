<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

test('job generates and stores embedding for a scrap', function () {
    Embeddings::fake([
        [Embeddings::fakeEmbedding(1024)],
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
    expect(json_decode($scrap->embedding, true))->toHaveCount(1024);
});

test('job does nothing when scrap does not exist', function () {
    Embeddings::fake([]);

    expect(fn () => GenerateScrapEmbeddingJob::dispatchSync(999999))->not->toThrow(Throwable::class);

    Embeddings::assertNothingGenerated();
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
