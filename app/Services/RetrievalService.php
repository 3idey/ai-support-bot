<?php
namespace App\Services;

use App\Models\Embedding;
use App\Helpers\VectorHelper;

class RetrievalService
{
    public function getRelevantChunks(array $queryEmbedding, int $limit = 5)
    {
        $results = [];

        foreach (Embedding::with('documentChunk')->get() as $embedding) {
            $score = VectorHelper::cosineSimilarity(
                $queryEmbedding,
                $embedding->embedding
            );

            $results[] = [
                'content' => $embedding->documentChunk->content,
                'score' => $score,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}
