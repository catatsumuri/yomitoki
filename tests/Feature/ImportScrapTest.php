<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\GenerateScrapSummaryJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('authenticated users can import a plain text file', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent('my-note.txt', 'This is the content.');

    $response = $this->withToken($token)
        ->post(route('api.scraps.import'), ['file' => $file]);

    $response->assertStatus(201);
    $scrap = Scrap::first();
    expect($scrap->title)->toBe('my-note');
    expect($scrap->content)->toBe('This is the content.');
    Queue::assertPushed(GenerateScrapEmbeddingJob::class);
    Queue::assertPushed(SummarizeScrapJob::class);
    Queue::assertNotPushed(GenerateScrapSummaryJob::class);
});

test('frontmatter title and tags are extracted from markdown files', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;
    $content = "---\ntitle: My Obsidian Note\ntags:\n  - obsidian\n  - yomitoki\n---\n\nBody content here.";
    $file = UploadedFile::fake()->createWithContent('note.md', $content);

    $this->withToken($token)
        ->post(route('api.scraps.import'), ['file' => $file]);

    $scrap = Scrap::first();
    expect($scrap->title)->toBe('My Obsidian Note');
    expect($scrap->content)->toBe('Body content here.');
    expect($scrap->meta['tags'])->toBe(['obsidian', 'yomitoki']);
    Queue::assertPushed(GenerateScrapSummaryJob::class);
    Queue::assertNotPushed(SummarizeScrapJob::class);
});

test('file without frontmatter uses filename as title', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent('getting-started.md', '## Introduction');

    $this->withToken($token)
        ->post(route('api.scraps.import'), ['file' => $file]);

    expect(Scrap::first()?->title)->toBe('getting-started');
});

test('project parameter is added to tags', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent('note.md', 'Content');

    $this->withToken($token)
        ->post(route('api.scraps.import'), ['file' => $file, 'project' => 'yomitoki']);

    expect(Scrap::first()?->meta['tags'])->toContain('yomitoki');
});

test('token without ingest ability is rejected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;
    $file = UploadedFile::fake()->createWithContent('note.md', 'Content');

    $this->withToken($token)
        ->post(route('api.scraps.import'), ['file' => $file])
        ->assertForbidden();
});
