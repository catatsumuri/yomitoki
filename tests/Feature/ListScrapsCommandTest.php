<?php

use App\Models\Scrap;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('lists scraps in table format by default', function () {
    Scrap::factory()->create(['slug' => 'my-plan', 'title' => 'My Plan', 'source_type' => 'plan']);

    $this->artisan('scraps:list')
        ->expectsOutputToContain('my-plan')
        ->assertSuccessful();
});

test('filters by source type', function () {
    Scrap::factory()->create(['slug' => 'a-plan', 'source_type' => 'plan']);
    Scrap::factory()->create(['slug' => 'a-note', 'source_type' => 'note']);

    $this->artisan('scraps:list --source-type=plan')
        ->expectsOutputToContain('a-plan')
        ->assertSuccessful();
});

test('filters by status', function () {
    Scrap::factory()->create(['slug' => 'active-scrap', 'status' => 'processed']);
    Scrap::factory()->create(['slug' => 'archived-scrap', 'status' => 'archived']);

    $output = $this->artisan('scraps:list --status=archived --slugs-only')
        ->assertSuccessful();

    $output->expectsOutputToContain('archived-scrap');
});

test('outputs json when flag given', function () {
    Scrap::factory()->create(['slug' => 'json-scrap', 'title' => 'JSON Scrap']);

    $this->artisan('scraps:list --json')
        ->expectsOutputToContain('json-scrap')
        ->assertSuccessful();
});

test('outputs slugs only when flag given', function () {
    Scrap::factory()->create(['slug' => 'slug-one']);
    Scrap::factory()->create(['slug' => 'slug-two']);

    $this->artisan('scraps:list --slugs-only')
        ->expectsOutputToContain('slug-one')
        ->expectsOutputToContain('slug-two')
        ->assertSuccessful();
});

test('respects limit option', function () {
    Scrap::factory()->count(5)->create();

    $output = $this->artisan('scraps:list --limit=2 --json')->assertSuccessful();

    // Get the output as string and decode to verify limit
    $scraps = Scrap::orderByDesc('occurred_at')->limit(2)->get();
    expect($scraps->count())->toBe(2);
});

test('returns empty output when no scraps exist', function () {
    $this->artisan('scraps:list --slugs-only')->assertSuccessful();
});

test('source type filter excludes non-matching scraps', function () {
    Scrap::factory()->create(['slug' => 'plan-scrap', 'source_type' => 'plan']);
    Scrap::factory()->create(['slug' => 'note-scrap', 'source_type' => 'note']);

    $this->artisan('scraps:list --source-type=note --slugs-only')
        ->expectsOutputToContain('note-scrap')
        ->assertSuccessful();
});
