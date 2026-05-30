<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('creates scrap from content string', function () {
    Queue::fake();
    User::factory()->create();

    $this->artisan('scraps:add --title="My Scrap" --content="Hello World"')
        ->assertSuccessful();

    expect(Scrap::count())->toBe(1);
    $scrap = Scrap::first();
    expect($scrap->title)->toBe('My Scrap')
        ->and($scrap->content)->toBe('Hello World')
        ->and($scrap->source_type)->toBe('note')
        ->and($scrap->status)->toBe('processed');
});

test('creates scrap from file', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'scrap-');
    file_put_contents($file, '# File Content');

    try {
        $this->artisan("scraps:add --title=\"File Scrap\" --file={$file}")
            ->assertSuccessful();

        expect(Scrap::first()->content)->toBe('# File Content');
    } finally {
        unlink($file);
    }
});

test('uses provided slug', function () {
    Queue::fake();
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --content="x" --slug="my-custom-slug"')
        ->assertSuccessful();

    expect(Scrap::first()->slug)->toBe('my-custom-slug');
});

test('auto-uniquifies duplicate slug', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->create(['slug' => 'my-scrap', 'user_id' => $user->id]);

    $this->artisan('scraps:add --title="Test" --content="x" --slug="my-scrap"')
        ->assertSuccessful();

    expect(Scrap::latest('id')->first()->slug)->toBe('my-scrap-2');
});

test('attaches to parent scrap', function () {
    Queue::fake();
    $user = User::factory()->create();
    $parent = Scrap::factory()->create(['slug' => 'parent-plan', 'user_id' => $user->id]);

    $this->artisan('scraps:add --title="Child" --content="x" --parent=parent-plan')
        ->assertSuccessful();

    expect(Scrap::latest('id')->first()->parent_id)->toBe($parent->id);
});

test('dispatches embedding and summary jobs', function () {
    Queue::fake();
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --content="x"')->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertPushed(SummarizeScrapJob::class);
});

test('skips summary job when description given', function () {
    Queue::fake();
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --content="x" --description="Short desc"')
        ->assertSuccessful();

    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertNotPushed(SummarizeScrapJob::class);
    expect(Scrap::first()->summary)->toBe('Short desc');
});

test('fails when title missing', function () {
    User::factory()->create();

    $this->artisan('scraps:add --content="x"')->assertFailed();
});

test('fails when neither content nor file given', function () {
    User::factory()->create();

    $this->artisan('scraps:add --title="Test"')->assertFailed();
});

test('fails when file not found', function () {
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --file=/nonexistent/file.md')->assertFailed();
});

test('fails when parent slug not found', function () {
    Queue::fake();
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --content="x" --parent=nonexistent')->assertFailed();
});

test('fails with invalid source type', function () {
    User::factory()->create();

    $this->artisan('scraps:add --title="Test" --content="x" --source-type=invalid')->assertFailed();
});
