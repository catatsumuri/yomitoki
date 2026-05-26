<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'document_type' => 'plan',
            'status' => 'draft',
            'content_markdown' => fake()->paragraphs(3, true),
            'summary' => null,
            'outline' => null,
            'meta' => null,
            'published_at' => null,
        ];
    }
}
