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
        Schema::create('scraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrap_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type');
            $table->string('source_reference')->nullable();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->longText('content_markdown')->nullable();
            $table->text('summary')->nullable();
            $table->string('status')->default('raw');
            $table->string('language', 12)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('extracted_data')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['source_type', 'occurred_at']);
            $table->index('source_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraps');
    }
};
