<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    private const EMBEDDING_MODEL = 'text-embedding-3-small';

    private const CACHE_TTL_DAYS = 7;

    private const EMBEDDING_DIMENSIONS = 1536;

    /**
     * Generate embedding for text or array of texts.
     *
     * @param  string|string[]  $text
     * @return array<int, float>|array<int, array<int, float>>
     */
    public function embed(string|array $text): array
    {
        $input = is_array($text) ? json_encode($text) : $text;
        $key = 'embedding:'.sha1($input);

        return Cache::remember($key, now()->addDays(self::CACHE_TTL_DAYS), fn () => $this->generateEmbedding($text));
    }

    private function generateEmbedding(string|array $text): array
    {
        try {
            return $this->callOpenAiApi($text);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return $this->generateMockEmbedding($text);
            }
            throw $e;
        }
    }

    /**
     * Call OpenAI API to generate embeddings
     */
    private function callOpenAiApi(string|array $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => self::EMBEDDING_MODEL,
            'input' => $text,
        ]);

        if (is_array($text)) {
            return array_map(fn ($item) => $item['embedding'], $response['data']);
        }

        return $response['data'][0]['embedding'];
    }

    /**
     * Generate mock embedding for local development
     */
    private function generateMockEmbedding(string|array $text): array
    {
        $count = is_array($text) ? count($text) : 1;
        $mockVector = array_fill(0, self::EMBEDDING_DIMENSIONS, 0.0);

        return is_array($text) ? array_fill(0, $count, $mockVector) : $mockVector;
    }
}
