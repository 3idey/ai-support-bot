<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    /**
     * Generate embedding for text or array of texts
     *
     * @param string|array $text
     * @return array
     */
    public function embed(string|array $text): array
    {
        try {
            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

            if (is_array($text)) {
                return array_map(fn($item) => $item['embedding'], $response['data']);
            }

            return $response['data'][0]['embedding'];
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                $count = is_array($text) ? count($text) : 1;
                $mock = array_fill(0, 1536, 0.0);
                return is_array($text) ? array_fill(0, $count, $mock) : $mock;
            }
            throw $e;
        }
    }
}
