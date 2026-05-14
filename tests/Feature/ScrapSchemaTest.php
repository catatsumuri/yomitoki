<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('ai defaults use bedrock where currently available', function () {
    expect(config('ai.default'))->toBe('bedrock');
    expect(config('ai.default_for_images'))->toBe('bedrock');
    expect(config('ai.default_for_embeddings'))->toBe('bedrock');
});

test('scrap domain tables are available with expected columns', function () {
    expect(Schema::hasTable('scrap_sources'))->toBeTrue();
    expect(Schema::hasTable('scraps'))->toBeTrue();
    expect(Schema::hasTable('scrap_relations'))->toBeTrue();
    expect(Schema::hasTable('documents'))->toBeTrue();
    expect(Schema::hasTable('document_scraps'))->toBeTrue();
    expect(Schema::hasTable('ai_runs'))->toBeTrue();
    expect(Schema::hasTable('agent_conversations'))->toBeTrue();
    expect(Schema::hasTable('agent_conversation_messages'))->toBeTrue();

    expect(Schema::hasColumns('scraps', [
        'user_id',
        'scrap_source_id',
        'source_type',
        'content',
        'status',
        'occurred_at',
        'extracted_data',
    ]))->toBeTrue();

    expect(Schema::hasColumns('documents', [
        'user_id',
        'document_type',
        'content_markdown',
        'status',
    ]))->toBeTrue();

    expect(Schema::hasColumns('ai_runs', [
        'user_id',
        'target_type',
        'target_id',
        'result_document_id',
        'run_type',
        'provider',
        'model',
        'status',
        'input_payload',
        'output_payload',
    ]))->toBeTrue();
});
