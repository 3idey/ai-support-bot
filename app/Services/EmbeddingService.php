<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    /**
     * Generate embedding for text
     */
    public function embed(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response['data'][0]['embedding'];
    }
}
