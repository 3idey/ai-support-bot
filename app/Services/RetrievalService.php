<?php

namespace App\Services;

use App\Helpers\VectorHelper;
use App\Models\Embedding;
use Illuminate\Support\Facades\Cache;

class RetrievalService
{
    public function getRelevantChunks(array $queryEmbedding, int $limit = 5)
    {
        $key = 'chunks:'.sha1(json_encode($queryEmbedding));

        return Cache::remember($key, now()->addMinutes(10), function () use ($queryEmbedding, $limit) {
            return $this->queryDatabase($queryEmbedding, $limit);
        });
    }

    /**
     * Query database for relevant chunks
     */
    private function queryDatabase(array $queryEmbedding, int $limit): array
    {
        $scores = [];

        foreach (Embedding::query()->select('id', 'document_chunk_id', 'embedding')->cursor() as $record) {
            $score = VectorHelper::cosineSimilarity(
                $queryEmbedding,
                $record->embedding
            );

            // keep track of score and chunk ID
            $scores[] = [
                'score' => $score,
                'document_chunk_id' => $record->document_chunk_id,
            ];
        }

        // sort by score descending
        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

        // take top results
        $topResults = array_slice($scores, 0, $limit);

        if (empty($topResults)) {
            return [];
        }

        // retrieve the actual content for only the top chunks
        $chunkIds = array_column($topResults, 'document_chunk_id');
        $chunks = \App\Models\DocumentChunk::whereIn('id', $chunkIds)->get()->keyBy('id');

        // build final result
        $finalResults = [];
        foreach ($topResults as $result) {
            if ($chunk = $chunks->get($result['document_chunk_id'])) {
                $finalResults[] = [
                    'content' => $chunk->content,
                    'score' => $result['score'],
                ];
            }
        }

        return $finalResults;
    }
}
