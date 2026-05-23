<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string[]>
     */
    private const TIMESTAMP_COLUMNS = [
        'users' => ['email_verified_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'passkeys' => ['last_used_at', 'created_at', 'updated_at'],
        'scrap_sources' => ['created_at', 'updated_at'],
        'scraps' => ['occurred_at', 'processed_at', 'embedding_generated_at', 'created_at', 'updated_at'],
        'scrap_relations' => ['created_at', 'updated_at'],
        'documents' => ['published_at', 'created_at', 'updated_at'],
        'ai_runs' => ['started_at', 'completed_at', 'created_at', 'updated_at'],
        'document_scraps' => ['created_at', 'updated_at'],
        'agent_conversations' => ['created_at', 'updated_at'],
        'agent_conversation_messages' => ['created_at', 'updated_at'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TIMESTAMP_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    sprintf(
                        'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE TIMESTAMP(0) WITH TIME ZONE USING "%s" AT TIME ZONE \'UTC\'',
                        $table,
                        $column,
                        $column,
                    ),
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TIMESTAMP_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    sprintf(
                        'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING "%s" AT TIME ZONE \'UTC\'',
                        $table,
                        $column,
                        $column,
                    ),
                );
            }
        }
    }
};
