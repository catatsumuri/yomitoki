<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('target');
            $table->foreignId('result_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('run_type');
            $table->string('agent_name')->nullable();
            $table->string('conversation_id', 36)->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('provider_run_id')->nullable();
            $table->string('status')->default('queued');
            $table->longText('prompt')->nullable();
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->json('usage')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['run_type', 'status']);
            $table->index('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
