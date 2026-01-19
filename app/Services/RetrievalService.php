<?php

namespace App\Services;

use App\Helpers\VectorHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    private const CACHE_TTL_MINUTES = 10;

    private const BATCH_SIZE = 100;

    public function getRelevantChunks(array $queryEmbedding, int $limit = 5, ?int $workspaceId = null): array
    {
        $key = 'chunks:'.sha1(json_encode($queryEmbedding).$workspaceId);

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($queryEmbedding, $limit, $workspaceId) {
            return $this->queryDatabase($queryEmbedding, $limit, $workspaceId);
        });
    }

    /**
     * Query database for relevant chunks
     */
    private function queryDatabase(array $queryEmbedding, int $limit, ?int $workspaceId): array
    {
        $embeddings = $this->fetchEmbeddings($workspaceId);
        $scores = $this->calculateSimilarityScores($embeddings, $queryEmbedding);
        $topResults = $this->getTopResults($scores, $limit);

        return $this->buildFinalResults($topResults);
    }

    /**
     * Fetch embeddings from database with optional workspace filter
     */
    private function fetchEmbeddings(?int $workspaceId): \Illuminate\Support\LazyCollection
    {
        $query = DB::table('embeddings')
            ->select('embeddings.id', 'embeddings.document_chunk_id', 'embeddings.embedding');

        if ($workspaceId) {
            $query->join('document_chunks', 'embeddings.document_chunk_id', '=', 'document_chunks.id')
                ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
                ->where('documents.workspace_id', $workspaceId);
        }

        return $query->cursor();
    }

    /**
     * Calculate cosine similarity scores for all embeddings
     */
    private function calculateSimilarityScores($embeddings, array $queryEmbedding): array
    {
        $scores = [];

        foreach ($embeddings as $record) {
            $embedding = json_decode($record->embedding, true);

            if (! is_array($embedding)) {
                continue;
            }

            $score = VectorHelper::cosineSimilarity($queryEmbedding, $embedding);

            $scores[] = [
                'score' => $score,
                'document_chunk_id' => $record->document_chunk_id,
            ];
        }

        return $scores;
    }

    /**
     * Sort scores and return top N results
     */
    private function getTopResults(array $scores, int $limit): array
    {
        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scores, 0, $limit);
    }

    /**
     * Build final results with chunk content
     */
    private function buildFinalResults(array $topResults): array
    {
        if (empty($topResults)) {
            return [];
        }

        $chunkIds = array_column($topResults, 'document_chunk_id');
        $chunks = \App\Models\DocumentChunk::whereIn('id', $chunkIds)->get()->keyBy('id');

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
