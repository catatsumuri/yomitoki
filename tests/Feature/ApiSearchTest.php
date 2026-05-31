<?php

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

uses(RefreshDatabase::class);

// --- Auth ---

test('search without token returns 401', function () {
    $this->getJson('/api/search?q=test')->assertUnauthorized();
});

test('search with token lacking ingest ability returns 403', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/search?q=test')->assertForbidden();
});

test('search without q returns 422', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->getJson('/api/search')->assertUnprocessable();
});

// --- Search ---

test('search returns matching scraps with similarity', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL with pgvector.');
    }

    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create([
        'slug' => 'auth-plan',
        'title' => 'Auth Plan',
        'embedding' => json_encode($vector),
        'status' => 'processed',
    ]);

    $response = $this->withToken($token)->getJson('/api/search?q=authentication');

    $response->assertOk()
        ->assertJsonStructure(['query', 'data'])
        ->assertJsonPath('query', 'authentication');

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.slug'))->toBe('auth-plan');
    expect($response->json('data.0.similarity'))->toBeNumeric();
    expect($response->json('data.0'))->toHaveKeys(['id', 'slug', 'title', 'source_type', 'status', 'summary', 'similarity', 'occurred_at', 'url']);
});

test('search does not return other users scraps', function () {
    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->create(['embedding' => json_encode($vector), 'status' => 'processed']); // other user

    $response = $this->withToken($token)->getJson('/api/search?q=test');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('search does not return archived scraps', function () {
    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create([
        'embedding' => json_encode($vector),
        'status' => 'archived',
    ]);

    $response = $this->withToken($token)->getJson('/api/search?q=test');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('search filters by source_type', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL with pgvector.');
    }

    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector], [$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'a-plan', 'source_type' => 'plan', 'embedding' => json_encode($vector), 'status' => 'processed']);
    Scrap::factory()->for($user)->create(['slug' => 'a-note', 'source_type' => 'note', 'embedding' => json_encode($vector), 'status' => 'processed']);

    $response = $this->withToken($token)->getJson('/api/search?q=test&source_type=plan');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.slug'))->toBe('a-plan');
});

test('search with include=content returns content_markdown', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL with pgvector.');
    }

    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create([
        'slug' => 'hello-scrap',
        'embedding' => json_encode($vector),
        'status' => 'processed',
        'content_markdown' => '# Hello',
    ]);

    $response = $this->withToken($token)->getJson('/api/search?q=test&include=content');

    $response->assertOk();
    expect($response->json('data.0'))->toHaveKey('content_markdown');
    expect($response->json('data.0.content_markdown'))->toBe('# Hello');
});

test('search without include does not return content_markdown', function () {
    $vector = array_fill(0, 1536, 0.1);
    Embeddings::fake([[$vector]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'no-content-scrap', 'embedding' => json_encode($vector), 'status' => 'processed']);

    $response = $this->withToken($token)->getJson('/api/search?q=test');

    $response->assertOk();
    expect($response->json('data.0'))->not->toHaveKey('content_markdown');
});

test('search returns empty data when no embeddings exist', function () {
    Embeddings::fake([[array_fill(0, 1536, 0.1)]]);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['embedding' => null]);

    $response = $this->withToken($token)->getJson('/api/search?q=test');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

// --- Related ---

test('related without token returns 401', function () {
    $this->getJson('/api/scraps/some-scrap/related')->assertUnauthorized();
});

test('related returns 404 for unknown slug', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->getJson('/api/scraps/nonexistent/related')->assertNotFound();
});

test('related returns related scraps', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires PostgreSQL with pgvector.');
    }

    $vector = array_fill(0, 1536, 0.1);

    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $scrap = Scrap::factory()->for($user)->create(['slug' => 'main-scrap', 'embedding' => json_encode($vector), 'status' => 'processed']);
    Scrap::factory()->for($user)->create(['slug' => 'related-scrap', 'embedding' => json_encode($vector), 'status' => 'processed']);

    $response = $this->withToken($token)->getJson('/api/scraps/main-scrap/related');

    $response->assertOk()
        ->assertJsonStructure(['data']);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0'))->toHaveKeys(['id', 'slug', 'title', 'source_type', 'status', 'summary', 'similarity']);
});

test('related returns empty when no embedding', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    Scrap::factory()->for($user)->create(['slug' => 'no-embedding', 'embedding' => null]);

    $response = $this->withToken($token)->getJson('/api/scraps/no-embedding/related');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});
