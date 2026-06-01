<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapTagsJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dispatches jobs for scraps without embeddings', function () {
    Queue::fake();

    $user = User::factory()->create();
    $unembedded = Scrap::factory()->count(3)->for($user)->create(['embedding' => null]);
    Scrap::factory()->for($user)->create(['embedding' => json_encode(array_fill(0, 1536, 0.1))]);

    $this->artisan('scraps:embed')
        ->expectsOutputToContain('Dispatched 3 embedding job(s)')
        ->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class, 3);

    foreach ($unembedded as $scrap) {
        Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn ($job) => $job->scrapId === $scrap->id);
    }
});

test('dispatches tag jobs when requested', function () {
    Queue::fake();

    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create(['embedding' => null]);

    $this->artisan('scraps:embed', ['--tags' => true])
        ->expectsOutputToContain('Dispatched 1 embedding job(s) + 1 tag job(s)')
        ->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn (GenerateScrapEmbeddingJob $job) => $job->scrapId === $scrap->id);
    Queue::assertPushed(GenerateScrapTagsJob::class, fn (GenerateScrapTagsJob $job) => $job->scrapId === $scrap->id);
});

test('forces embedded scraps to be reprocessed', function () {
    Queue::fake();

    $user = User::factory()->create();
    $embedded = Scrap::factory()->for($user)->create(['embedding' => json_encode(array_fill(0, 1536, 0.1))]);
    $pending = Scrap::factory()->for($user)->create(['embedding' => null]);

    $this->artisan('scraps:embed', ['--force' => true])
        ->expectsOutputToContain('Dispatched 2 embedding job(s)')
        ->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class, 2);
    Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn (GenerateScrapEmbeddingJob $job) => $job->scrapId === $embedded->id);
    Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn (GenerateScrapEmbeddingJob $job) => $job->scrapId === $pending->id);
});

test('does nothing when all scraps already have embeddings', function () {
    Queue::fake();

    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['embedding' => json_encode(array_fill(0, 1536, 0.1))]);

    $this->artisan('scraps:embed')
        ->expectsOutputToContain('No scraps to process')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
