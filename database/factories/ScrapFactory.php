<?php

namespace Database\Factories;

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scrap>
 */
class ScrapFactory extends Factory
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
            'parent_id' => null,
            'scrap_source_id' => null,
            'source_type' => 'note',
            'source_reference' => fake()->uuid(),
            'title' => fake()->sentence(4),
            'slug' => fake()->optional()->slug(),
            'content' => fake()->paragraph(),
            'content_markdown' => fake()->paragraph(),
            'summary' => fake()->sentence(),
            'status' => 'raw',
            'language' => 'ja',
            'occurred_at' => now(),
            'processed_at' => null,
            'extracted_data' => null,
            'meta' => null,
        ];
    }
}
