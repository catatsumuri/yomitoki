<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// --- Auth ---

test('index without token returns 401', function () {
    $this->getJson('/api/scraps')->assertUnauthorized();
});

test('index with token lacking ingest ability returns 403', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/scraps')->assertForbidden();
});

// --- Index ---

test('index returns scraps for authenticated user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->count(3)->for($user)->create();
    Scrap::factory()->create(); // other user

    $response = $this->withToken($token)->getJson('/api/scraps');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonCount(3, 'data');
});

test('index filters by source_type', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['source_type' => 'plan', 'slug' => 'a-plan']);
    Scrap::factory()->for($user)->create(['source_type' => 'note', 'slug' => 'a-note']);

    $response = $this->withToken($token)->getJson('/api/scraps?source_type=plan');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.slug'))->toBe('a-plan');
});

test('index filters by status', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['status' => 'processed', 'slug' => 'active']);
    Scrap::factory()->for($user)->create(['status' => 'archived', 'slug' => 'archived']);

    $response = $this->withToken($token)->getJson('/api/scraps?status=archived');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.slug'))->toBe('archived');
});

test('index does not return other users scraps', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;
    Scrap::factory()->count(5)->create(); // other users

    $this->withToken($token)->getJson('/api/scraps')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// --- Show ---

test('show returns scrap with children', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $parent = Scrap::factory()->for($user)->create(['slug' => 'parent-scrap']);
    Scrap::factory()->for($user)->create(['parent_id' => $parent->id]);

    $this->withToken($token)->getJson('/api/scraps/parent-scrap')
        ->assertOk()
        ->assertJsonStructure(['slug', 'title', 'children'])
        ->assertJsonCount(1, 'children');
});

test('show returns 404 for unknown slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->getJson('/api/scraps/does-not-exist')->assertNotFound();
});

test('show returns 404 for other users scrap', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->create(['slug' => 'other-scrap']); // different user

    $this->withToken($token)->getJson('/api/scraps/other-scrap')->assertNotFound();
});

// --- Update ---

test('update changes title', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'my-scrap']);

    $this->withToken($token)->patchJson('/api/scraps/my-scrap', ['title' => 'New Title'])
        ->assertOk()
        ->assertJsonPath('title', 'New Title');
});

test('update uniquifies slug on conflict', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'existing-slug']);
    Scrap::factory()->for($user)->create(['slug' => 'my-scrap']);

    $response = $this->withToken($token)->patchJson('/api/scraps/my-scrap', ['slug' => 'existing-slug']);

    $response->assertOk();
    expect($response->json('slug'))->toBe('existing-slug-2');
});

test('update dispatches jobs when content changed', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'my-scrap']);

    $this->withToken($token)->patchJson('/api/scraps/my-scrap', ['content_markdown' => '# Updated']);

    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertPushed(SummarizeScrapJob::class);
});

test('update does not dispatch jobs when only title changed', function () {
    Queue::fake();
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'my-scrap']);

    $this->withToken($token)->patchJson('/api/scraps/my-scrap', ['title' => 'New Title']);

    Queue::assertNotPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertNotPushed(SummarizeScrapJob::class);
});

test('update returns 404 for unknown slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->patchJson('/api/scraps/nonexistent', ['title' => 'x'])->assertNotFound();
});

// --- Destroy ---

test('destroy deletes scrap and children', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $parent = Scrap::factory()->for($user)->create(['slug' => 'parent']);
    Scrap::factory()->for($user)->create(['parent_id' => $parent->id]);

    $this->withToken($token)->deleteJson('/api/scraps/parent')->assertNoContent();

    expect(Scrap::count())->toBe(0);
});

test('destroy returns 404 for unknown slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->deleteJson('/api/scraps/nonexistent')->assertNotFound();
});

test('destroy returns 404 for other users scrap', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->create(['slug' => 'other-scrap']); // different user

    $this->withToken($token)->deleteJson('/api/scraps/other-scrap')->assertNotFound();
});
