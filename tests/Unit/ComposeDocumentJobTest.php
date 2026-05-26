<?php

use App\Ai\Agents\ComposeDocumentAgent;
use App\Jobs\ComposeDocumentJob;
use App\Models\AiRun;
use App\Models\Document;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('job creates a document and marks ai_run as completed', function () {
    ComposeDocumentAgent::fake([[
        'title' => 'Combined Spec',
        'content_markdown' => '# Spec\n\nContent here.',
        'summary' => '仕様書のサマリーです。',
        'outline' => ['概要', '詳細'],
    ]]);

    $user = User::factory()->create();
    $aiRun = AiRun::create([
        'user_id' => $user->id,
        'run_type' => 'compose_document',
        'agent_name' => ComposeDocumentAgent::class,
        'status' => 'queued',
        'input_payload' => ['scrap_ids' => [], 'document_type' => 'spec'],
    ]);

    $scrap1 = Scrap::factory()->for($user)->create(['title' => 'Plan A', 'content_markdown' => 'Plan A content.']);
    $scrap2 = Scrap::factory()->for($user)->create(['title' => 'Plan B', 'content_markdown' => 'Plan B content.']);

    $job = new ComposeDocumentJob($user->id, [$scrap1->id, $scrap2->id], 'spec', $aiRun->id);
    $job->handle();

    // Document was created
    $document = Document::query()->where('user_id', $user->id)->first();
    expect($document)->not->toBeNull();
    expect($document->title)->toBe('Combined Spec');
    expect($document->document_type)->toBe('spec');
    expect($document->summary)->toBe('仕様書のサマリーです。');
    expect($document->outline)->toBe(['概要', '詳細']);

    // Pivot rows inserted
    $this->assertDatabaseHas('document_scraps', [
        'document_id' => $document->id,
        'scrap_id' => $scrap1->id,
        'position' => 0,
        'role' => 'source',
    ]);
    $this->assertDatabaseHas('document_scraps', [
        'document_id' => $document->id,
        'scrap_id' => $scrap2->id,
        'position' => 1,
        'role' => 'source',
    ]);

    // AiRun updated
    $aiRun->refresh();
    expect($aiRun->status)->toBe('completed');
    expect($aiRun->result_document_id)->toBe($document->id);
    expect($aiRun->completed_at)->not->toBeNull();
});

test('job preserves scrap ordering from input ids', function () {
    ComposeDocumentAgent::fake([[
        'title' => 'Ordered Spec',
        'content_markdown' => '# Content',
        'summary' => 'Summary.',
        'outline' => [],
    ]]);

    $user = User::factory()->create();
    $scrap1 = Scrap::factory()->for($user)->create(['title' => 'First']);
    $scrap2 = Scrap::factory()->for($user)->create(['title' => 'Second']);
    $scrap3 = Scrap::factory()->for($user)->create(['title' => 'Third']);

    // Pass IDs in reverse order
    $job = new ComposeDocumentJob($user->id, [$scrap3->id, $scrap1->id, $scrap2->id]);
    $job->handle();

    $document = Document::query()->where('user_id', $user->id)->first();

    $this->assertDatabaseHas('document_scraps', ['document_id' => $document->id, 'scrap_id' => $scrap3->id, 'position' => 0]);
    $this->assertDatabaseHas('document_scraps', ['document_id' => $document->id, 'scrap_id' => $scrap1->id, 'position' => 1]);
    $this->assertDatabaseHas('document_scraps', ['document_id' => $document->id, 'scrap_id' => $scrap2->id, 'position' => 2]);
});

test('job skips scraps belonging to other users', function () {
    ComposeDocumentAgent::fake([[
        'title' => 'Single Spec',
        'content_markdown' => '# Content',
        'summary' => 'Summary.',
        'outline' => [],
    ]]);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownScrap = Scrap::factory()->for($user)->create(['title' => 'Mine']);
    $otherScrap = Scrap::factory()->for($otherUser)->create(['title' => 'Theirs']);

    $job = new ComposeDocumentJob($user->id, [$ownScrap->id, $otherScrap->id]);
    $job->handle();

    $document = Document::query()->where('user_id', $user->id)->first();
    expect($document)->not->toBeNull();

    $this->assertDatabaseHas('document_scraps', ['document_id' => $document->id, 'scrap_id' => $ownScrap->id]);
    $this->assertDatabaseMissing('document_scraps', ['document_id' => $document->id, 'scrap_id' => $otherScrap->id]);
});

test('failed method marks ai_run as failed', function () {
    $user = User::factory()->create();
    $aiRun = AiRun::create([
        'user_id' => $user->id,
        'run_type' => 'compose_document',
        'agent_name' => ComposeDocumentAgent::class,
        'status' => 'running',
        'input_payload' => ['scrap_ids' => [], 'document_type' => 'spec'],
    ]);

    $job = new ComposeDocumentJob($user->id, [], 'spec', $aiRun->id);
    $job->failed(new RuntimeException('Something went wrong'));

    $aiRun->refresh();
    expect($aiRun->status)->toBe('failed');
    expect($aiRun->error_message)->toBe('Something went wrong');
    expect($aiRun->completed_at)->not->toBeNull();
});

test('job marks ai_run as failed when no valid scraps found', function () {
    $user = User::factory()->create();
    $aiRun = AiRun::create([
        'user_id' => $user->id,
        'run_type' => 'compose_document',
        'agent_name' => ComposeDocumentAgent::class,
        'status' => 'queued',
        'input_payload' => ['scrap_ids' => [99999], 'document_type' => 'spec'],
    ]);

    $job = new ComposeDocumentJob($user->id, [99999], 'spec', $aiRun->id);
    $job->handle();

    $aiRun->refresh();
    expect($aiRun->status)->toBe('failed');
    expect($aiRun->error_message)->toContain('No valid scraps');
    expect(Document::count())->toBe(0);
});
