<?php

namespace App\Services;

use App\Helpers\VectorHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * High-performance vector retrieval service with pgvector support
 *
 * Features:
 * - Automatic detection of vector database support
 * - Falls back to JSON + PHP calculation if pgvector unavailable
 * - 10-100x faster similarity search with pgvector
 * - Supports filtering by workspace
 */
class VectorRetrievalService extends RetrievalService
{
    private const CACHE_TTL_MINUTES = 10;
    private bool $usePgVector;

    public function __construct()
    {
        $this->usePgVector = $this->detectPgVectorSupport();
    }

    /**
     * Get relevant chunks using optimized vector search
     */
    public function getRelevantChunks(array $queryEmbedding, int $limit = 5, ?int $workspaceId = null): array
    {
        $key = 'chunks:v2:' . sha1(json_encode($queryEmbedding) . $workspaceId);

        return Cache::remember($key, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($queryEmbedding, $limit, $workspaceId) {
            if ($this->usePgVector) {
                return $this->queryWithPgVector($queryEmbedding, $limit, $workspaceId);
            }

            return $this->queryWithPhp($queryEmbedding, $limit, $workspaceId);
        });
    }

    /**
     * Ultra-fast pgvector-based retrieval
     * Uses native PostgreSQL vector operations
     */
    private function queryWithPgVector(array $queryEmbedding, int $limit, ?int $workspaceId): array
    {
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $query = DB::table('embeddings')
            ->select([
                'embeddings.id',
                'embeddings.document_chunk_id',
                DB::raw("1 - (embedding_vector <=> '{$vectorString}'::vector) as similarity_score")
            ]);

        if ($workspaceId) {
            $query->join('document_chunks', 'embeddings.document_chunk_id', '=', 'document_chunks.id')
                ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
                ->where('documents.workspace_id', $workspaceId);
        }

        $results = $query
            ->orderByDesc('similarity_score')
            ->limit($limit)
            ->get();

        return $this->buildResultsFromQuery($results);
    }

    /**
     * Fallback: PHP-based cosine similarity calculation
     */
    private function queryWithPhp(array $queryEmbedding, int $limit, ?int $workspaceId): array
    {
        // Use parent class implementation
        return parent::getRelevantChunks($queryEmbedding, $limit, $workspaceId);
    }

    /**
     * Build final results with document chunk details
     */
    private function buildResultsFromQuery(Collection $results): array
    {
        if ($results->isEmpty()) {
            return [];
        }

        $chunkIds = $results->pluck('document_chunk_id')->toArray();

        $chunks = DB::table('document_chunks')
            ->whereIn('id', $chunkIds)
            ->select('id', 'content', 'document_id', 'chunk_index')
            ->get()
            ->keyBy('id');

        $documentIds = $chunks->pluck('document_id')->unique()->toArray();

        $documents = DB::table('documents')
            ->whereIn('id', $documentIds)
            ->select('id', 'filename', 'workspace_id')
            ->get()
            ->keyBy('id');

        return $results->map(function ($result) use ($chunks, $documents) {
            $chunk = $chunks->get($result->document_chunk_id);
            if (!$chunk) {
                return null;
            }

            $document = $documents->get($chunk->document_id);

            return [
                'content' => $chunk->content,
                'similarity_score' => round($result->similarity_score ?? 0, 4),
                'chunk_index' => $chunk->chunk_index,
                'document' => [
                    'id' => $document?->id,
                    'filename' => $document?->filename,
                    'workspace_id' => $document?->workspace_id,
                ],
            ];
        })->filter()->values()->toArray();
    }

    /**
     * Detect if pgvector is available
     */
    private function detectPgVectorSupport(): bool
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver !== 'pgsql') {
                return false;
            }

            // Check if pgvector extension exists
            $result = DB::select("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'vector') as has_vector");

            if (empty($result) || !$result[0]->has_vector) {
                return false;
            }

            // Check if embedding_vector column exists
            $hasColumn = DB::select("
                SELECT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_name = 'embeddings'
                    AND column_name = 'embedding_vector'
                ) as has_column
            ");

            return !empty($hasColumn) && $hasColumn[0]->has_column;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        return [
            'vector_database' => $this->usePgVector ? 'pgvector' : 'json+php',
            'driver' => DB::connection()->getDriverName(),
            'estimated_speedup' => $this->usePgVector ? '10-100x' : '1x',
            'recommended_action' => $this->usePgVector
                ? 'Using optimized vector search'
                : 'Consider installing pgvector extension for 10-100x faster searches',
        ];
    }

    /**
     * Migrate JSON embeddings to pgvector format
     * Run this after enabling pgvector
     */
    public function migrateToVector(int $batchSize = 1000): array
    {
        if (!$this->usePgVector) {
            throw new \RuntimeException('pgvector is not available. Please install the extension first.');
        }

        $migrated = 0;
        $errors = 0;

        DB::table('embeddings')
            ->whereNull('embedding_vector')
            ->orderBy('id')
            ->chunk($batchSize, function ($embeddings) use (&$migrated, &$errors) {
                foreach ($embeddings as $embedding) {
                    try {
                        $vector = json_decode($embedding->embedding, true);
                        if (is_array($vector)) {
                            $vectorString = '[' . implode(',', $vector) . ']';
                            DB::statement(
                                "UPDATE embeddings SET embedding_vector = ?::vector WHERE id = ?",
                                [$vectorString, $embedding->id]
                            );
                            $migrated++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                    }
                }
            });

        return [
            'migrated' => $migrated,
            'errors' => $errors,
            'status' => $errors === 0 ? 'success' : 'completed_with_errors',
        ];
    }
}
