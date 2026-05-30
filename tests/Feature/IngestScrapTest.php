<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('request without token returns 401', function () {
    $this->postJson('/api/scraps', [
        'title' => 'Test',
        'content_markdown' => '# Test',
    ])->assertUnauthorized();
});

test('request with invalid token returns 401', function () {
    $this->withToken('invalid-token')
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
        ])->assertUnauthorized();
});

test('valid token without ingest ability returns 403', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
        ])->assertForbidden();
});

test('valid token with minimal payload creates scrap', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'My Scrap',
            'content_markdown' => '# My Scrap',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'slug', 'url']);

    expect(Scrap::count())->toBe(1);
    $scrap = Scrap::first();
    expect($scrap->title)->toBe('My Scrap')
        ->and($scrap->source_type)->toBe('plan')
        ->and($scrap->status)->toBe('processed');
});

test('source_type defaults to plan when omitted', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
        ]);

    expect(Scrap::first()->source_type)->toBe('plan');
});

test('project is stored in meta tags', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
            'project' => 'my-app',
        ]);

    $scrap = Scrap::first();
    expect($scrap->meta['project'])->toBe('my-app')
        ->and($scrap->meta['tags'])->toContain('my-app');
});

test('created_from is always set to external-ingest-api', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
            'meta' => ['created_from' => 'something-else'],
        ]);

    expect(Scrap::first()->meta['created_from'])->toBe('external-ingest-api');
});

test('duplicate slug is uniquified with suffix', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->create(['slug' => 'my-scrap', 'user_id' => $user->id]);

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'My Scrap',
            'content_markdown' => '# Test',
            'slug' => 'my-scrap',
        ]);

    expect(Scrap::latest('id')->first()->slug)->toBe('my-scrap-2');
});

test('summarize and embedding jobs are dispatched', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
        ]);

    Queue::assertPushed(SummarizeScrapJob::class);
    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
});

// --- parent_slug ---

test('parent_slug creates child scrap', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $parent = Scrap::factory()->for($user)->create(['slug' => 'parent-plan']);

    $response = $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Result',
            'content_markdown' => '# Result',
            'source_type' => 'execution',
            'parent_slug' => 'parent-plan',
        ]);

    $response->assertCreated();
    $child = Scrap::latest('id')->first();
    expect($child->parent_id)->toBe($parent->id);
});

test('parent_slug from another user returns 422', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->create(['slug' => 'other-plan']); // different user

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Result',
            'content_markdown' => '# Result',
            'parent_slug' => 'other-plan',
        ])
        ->assertUnprocessable();
});

// --- description ---

test('description is saved as summary and skips SummarizeScrapJob', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Test',
            'content_markdown' => '# Test',
            'description' => 'Short summary',
        ]);

    $scrap = Scrap::first();
    expect($scrap->summary)->toBe('Short summary');
    Queue::assertNotPushed(SummarizeScrapJob::class);
    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
});

// --- source_type execution ---

test('source_type execution is accepted', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/scraps', [
            'title' => 'Exec',
            'content_markdown' => '# Exec',
            'source_type' => 'execution',
        ])
        ->assertCreated();

    expect(Scrap::first()->source_type)->toBe('execution');
});
