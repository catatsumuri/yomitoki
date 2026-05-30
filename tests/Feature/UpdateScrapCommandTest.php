<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('updates title', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap', 'title' => 'Old Title']);

    $this->artisan('scraps:update --slug=my-scrap --title="New Title"')
        ->assertSuccessful();

    expect(Scrap::first()->title)->toBe('New Title');
});

test('updates content from string', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:update --slug=my-scrap --content="Updated content"')
        ->assertSuccessful();

    $scrap = Scrap::first();
    expect($scrap->content)->toBe('Updated content')
        ->and($scrap->content_markdown)->toBe('Updated content');
});

test('updates content from file', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $file = tempnam(sys_get_temp_dir(), 'scrap-');
    file_put_contents($file, '# Updated from file');

    try {
        $this->artisan("scraps:update --slug=my-scrap --file={$file}")->assertSuccessful();
        expect(Scrap::first()->content)->toBe('# Updated from file');
    } finally {
        unlink($file);
    }
});

test('updates status', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap', 'status' => 'raw']);

    $this->artisan('scraps:update --slug=my-scrap --status=archived')->assertSuccessful();

    expect(Scrap::first()->status)->toBe('archived');
});

test('updates slug with uniquification when no conflict', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:update --slug=my-scrap --new-slug=renamed-scrap')->assertSuccessful();

    expect(Scrap::first()->slug)->toBe('renamed-scrap');
});

test('updates slug with uniquification when conflict exists', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'target-slug']);
    Scrap::factory()->create(['slug' => 'other-scrap']);

    $this->artisan('scraps:update --slug=other-scrap --new-slug=target-slug')->assertSuccessful();

    expect(Scrap::where('slug', 'other-scrap')->exists())->toBeFalse();
    expect(Scrap::latest('id')->first()->slug)->toBe('target-slug-2');
});

test('dispatches jobs when content updated', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:update --slug=my-scrap --content="New content"')->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertPushed(SummarizeScrapJob::class);
});

test('does not dispatch jobs when only title updated', function () {
    Queue::fake();
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:update --slug=my-scrap --title="New Title"')->assertSuccessful();

    Queue::assertNotPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertNotPushed(SummarizeScrapJob::class);
});

test('fails when slug missing', function () {
    $this->artisan('scraps:update --title="Test"')->assertFailed();
});

test('fails when scrap not found', function () {
    $this->artisan('scraps:update --slug=nonexistent --title="Test"')->assertFailed();
});

test('fails when both content and file given', function () {
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $file = tempnam(sys_get_temp_dir(), 'scrap-');
    try {
        $this->artisan("scraps:update --slug=my-scrap --content=x --file={$file}")->assertFailed();
    } finally {
        unlink($file);
    }
});

test('warns when no fields to update', function () {
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:update --slug=my-scrap')
        ->expectsOutputToContain('No fields to update')
        ->assertSuccessful();
});
