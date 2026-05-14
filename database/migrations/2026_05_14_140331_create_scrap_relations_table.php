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
        Schema::create('scrap_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_scrap_id')->constrained('scraps')->cascadeOnDelete();
            $table->foreignId('to_scrap_id')->constrained('scraps')->cascadeOnDelete();
            $table->string('relation_type');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['from_scrap_id', 'to_scrap_id', 'relation_type']);
            $table->index(['to_scrap_id', 'relation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrap_relations');
    }
};
