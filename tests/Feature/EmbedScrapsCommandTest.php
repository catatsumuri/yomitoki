<?php

use App\Jobs\GenerateScrapEmbeddingJob;
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
        ->expectsOutputToContain('Dispatching embedding jobs for 3 scraps')
        ->expectsOutputToContain('Dispatched 3 jobs')
        ->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class, 3);

    foreach ($unembedded as $scrap) {
        Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn ($job) => $job->scrapId === $scrap->id);
    }
});

test('does nothing when all scraps already have embeddings', function () {
    Queue::fake();

    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['embedding' => json_encode(array_fill(0, 1536, 0.1))]);

    $this->artisan('scraps:embed')
        ->expectsOutputToContain('All scraps already have embeddings')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
