<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
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
            'workspace_id' => \App\Models\Workspace::factory(),
            'title' => $this->faker->sentence,
            'source_type' => 'file',
            'file_path' => 'documents/test.pdf',
            'chunk_count' => 0,
            'processed' => false,
            'status' => 'pending',
        ];
    }
}
