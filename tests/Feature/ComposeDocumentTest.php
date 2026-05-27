<?php

use App\Jobs\ComposeDocumentJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('authenticated users can dispatch a compose document job', function () {
    $user = User::factory()->create();
    $scrap1 = Scrap::factory()->for($user)->create(['title' => 'Plan A', 'status' => 'final']);
    $scrap2 = Scrap::factory()->for($user)->create(['title' => 'Plan B', 'status' => 'final']);

    $response = $this
        ->actingAs($user)
        ->post(route('documents.compose'), [
            'ids' => [$scrap1->id, $scrap2->id],
            'document_type' => 'spec',
        ]);

    $response->assertRedirect();

    Queue::assertPushed(ComposeDocumentJob::class, function (ComposeDocumentJob $job) use ($scrap1, $scrap2) {
        return $job->scrapIds === [$scrap1->id, $scrap2->id]
            && $job->documentType === 'spec';
    });

    $this->assertDatabaseHas('ai_runs', [
        'user_id' => $user->id,
        'run_type' => 'compose_document',
        'status' => 'queued',
    ]);
});

test('document_type defaults to spec when omitted', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create();

    $this->actingAs($user)->post(route('documents.compose'), [
        'ids' => [$scrap->id],
    ]);

    Queue::assertPushed(ComposeDocumentJob::class, fn (ComposeDocumentJob $job) => $job->documentType === 'spec');
});

test('ids array is required', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('documents.compose'), [])
        ->assertSessionHasErrors('ids');

    Queue::assertNothingPushed();
});

test('invalid document_type is rejected', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create();

    $this
        ->actingAs($user)
        ->post(route('documents.compose'), [
            'ids' => [$scrap->id],
            'document_type' => 'invalid',
        ])
        ->assertSessionHasErrors('document_type');

    Queue::assertNothingPushed();
});

test('guests are redirected', function () {
    $this
        ->post(route('documents.compose'), ['ids' => [1]])
        ->assertRedirect(route('login'));
});
