<?php

use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('dashboard demo seeder creates prototype data', function () {
    $this->seed(DashboardDemoSeeder::class);

    $userId = DB::table('users')
        ->where('email', 'test@example.com')
        ->value('id');

    expect($userId)->not->toBeNull();

    $this->assertDatabaseCount('scrap_sources', 4);
    $this->assertDatabaseCount('scraps', 12);
    $this->assertDatabaseCount('scrap_relations', 5);
    $this->assertDatabaseCount('documents', 4);
    $this->assertDatabaseCount('document_scraps', 13);
    $this->assertDatabaseCount('ai_runs', 6);
    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseCount('agent_conversation_messages', 2);

    $this->assertDatabaseHas('documents', [
        'user_id' => $userId,
        'title' => '仕様書生成フロー草案',
        'document_type' => 'spec',
    ]);

    $this->assertDatabaseHas('scraps', [
        'user_id' => $userId,
        'source_reference' => 's4',
        'status' => 'raw',
    ]);

    expect(DB::table('scraps')
        ->whereNull('parent_id')
        ->whereNull('slug')
        ->count())->toBe(0);

    expect(DB::table('scraps')
        ->whereNotNull('parent_id')
        ->whereNotNull('slug')
        ->count())->toBe(0);

    $this->assertDatabaseHas('ai_runs', [
        'user_id' => $userId,
        'provider' => 'bedrock',
        'run_type' => 'generate_document',
        'status' => 'failed',
    ]);
});
