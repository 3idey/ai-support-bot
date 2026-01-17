<?php

namespace App\Services;

use App\Helpers\VectorHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RetrievalService
{
    public function getRelevantChunks(array $queryEmbedding, int $limit = 5, ?int $workspaceId = null)
    {
        $key = 'chunks:' . sha1(json_encode($queryEmbedding) . $workspaceId);

        return Cache::remember($key, now()->addMinutes(10), function () use ($queryEmbedding, $limit, $workspaceId) {
            return $this->queryDatabase($queryEmbedding, $limit, $workspaceId);
        });
    }

    /**
     * Query database for relevant chunks
     */
    private function queryDatabase(array $queryEmbedding, int $limit, ?int $workspaceId): array
    {
        $scores = [];

        $query = DB::table('embeddings')
            ->select('embeddings.id', 'embeddings.document_chunk_id', 'embeddings.embedding');

        if ($workspaceId) {
            $query->join('document_chunks', 'embeddings.document_chunk_id', '=', 'document_chunks.id')
                ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
                ->where('documents.workspace_id', $workspaceId);
        }

        foreach ($query->cursor() as $record) {
            $embedding = json_decode($record->embedding, true);

            if (!is_array($embedding)) {
                continue;
            }

            $score = VectorHelper::cosineSimilarity(
                $queryEmbedding,
                $embedding
            );

            // keep track of score and chunk ID
            $scores[] = [
                'score' => $score,
                'document_chunk_id' => $record->document_chunk_id,
            ];
        }

        // sort by score descending
        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

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
