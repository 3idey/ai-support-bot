<?php

namespace App\Http\Controllers;

use App\Services\VectorRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vector Database Management Controller
 *
 * Provides endpoints for managing vector storage and performance
 */
class VectorController extends Controller
{
    public function __construct(
        protected VectorRetrievalService $vectorService
    ) {}

    /**
     * Get vector database status and performance info
     *
     * GET /api/vectors/status
     */
    public function status(): JsonResponse
    {
        $stats = $this->vectorService->getPerformanceStats();

        $totalEmbeddings = DB::table('embeddings')->count();
        $totalChunks = DB::table('document_chunks')->count();

        $avgDimensions = null;
        if ($totalEmbeddings > 0) {
            $sample = DB::table('embeddings')->first();
            if ($sample && $sample->embedding) {
                $vector = json_decode($sample->embedding, true);
                $avgDimensions = is_array($vector) ? count($vector) : null;
            }
        }

        return response()->json([
            'status' => 'ok',
            'vector_storage' => $stats,
            'statistics' => [
                'total_embeddings' => $totalEmbeddings,
                'total_chunks' => $totalChunks,
                'vector_dimensions' => $avgDimensions,
            ],
            'supported_databases' => [
                'pgvector' => [
                    'available' => $stats['vector_database'] === 'pgvector',
                    'description' => 'PostgreSQL with pgvector extension',
                    'performance' => '10-100x faster',
                ],
                'json_php' => [
                    'available' => true,
                    'description' => 'JSON storage with PHP cosine similarity',
                    'performance' => 'Baseline',
                ],
            ],
        ]);
    }

    /**
     * Migrate embeddings from JSON to pgvector format
     *
     * POST /api/vectors/migrate
     * Body: { "batch_size": 1000 }
     */
    public function migrate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_size' => 'nullable|integer|min:100|max:10000',
        ]);

        try {
            $result = $this->vectorService->migrateToVector(
                $validated['batch_size'] ?? 1000
            );

            return response()->json([
                'success' => true,
                'message' => 'Migration completed',
                'result' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'help' => 'Install pgvector: CREATE EXTENSION vector; then run migration: php artisan migrate',
            ], 400);
        }
    }

    /**
     * Test vector search performance
     *
     * POST /api/vectors/benchmark
     * Body: { "query": "test query", "iterations": 10 }
     */
    public function benchmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:500',
            'iterations' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $validated['query'] ?? 'test search performance query';
        $iterations = $validated['iterations'] ?? 10;

        // Create a dummy embedding (in real scenario, you'd use EmbeddingService)
        $dummyEmbedding = array_fill(0, 1536, 0.1);

        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $this->vectorService->getRelevantChunks($dummyEmbedding, 5);
            $times[] = (microtime(true) - $start) * 1000; // Convert to ms
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        return response()->json([
            'query' => $query,
            'iterations' => $iterations,
            'performance' => [
                'average_ms' => round($avgTime, 2),
                'min_ms' => round($minTime, 2),
                'max_ms' => round($maxTime, 2),
                'total_ms' => round(array_sum($times), 2),
            ],
            'vector_database' => $this->vectorService->getPerformanceStats()['vector_database'],
        ]);
    }

    /**
     * Get recommendations for vector database optimization
     *
     * GET /api/vectors/recommendations
     */
    public function recommendations(): JsonResponse
    {
        $stats = $this->vectorService->getPerformanceStats();
        $driver = DB::connection()->getDriverName();
        $totalEmbeddings = DB::table('embeddings')->count();

        $recommendations = [];

        // Database-specific recommendations
        if ($driver !== 'pgsql' && $totalEmbeddings > 10000) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Switch to PostgreSQL with pgvector',
                'description' => 'Your dataset has grown. PostgreSQL with pgvector provides 10-100x faster similarity search.',
                'action' => 'Migrate to PostgreSQL and install pgvector extension',
            ];
        }

        if ($driver === 'pgsql' && $stats['vector_database'] !== 'pgvector') {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Enable pgvector extension',
                'description' => 'PostgreSQL detected but pgvector not enabled. Enable for massive performance boost.',
                'action' => 'Run: CREATE EXTENSION vector; then: php artisan migrate',
            ];
        }

        // Scale recommendations
        if ($totalEmbeddings > 100000 && $stats['vector_database'] === 'json+php') {
            $recommendations[] = [
                'priority' => 'critical',
                'title' => 'Critical: Vector database required',
                'description' => 'With 100K+ embeddings, PHP-based search will be very slow.',
                'alternatives' => [
                    'pgvector (PostgreSQL) - Best for self-hosted',
                    'Pinecone - Managed service, easy scaling',
                    'Qdrant - High performance, good for large scale',
                ],
            ];
        }

        if ($totalEmbeddings > 1000000) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Consider specialized vector database',
                'description' => 'With 1M+ vectors, specialized solutions offer better performance.',
                'options' => [
                    'Qdrant - Excellent for large-scale deployments',
                    'Weaviate - Advanced filtering and hybrid search',
                    'Pinecone - Fully managed, excellent scaling',
                ],
            ];
        }

        // Indexing recommendations
        if ($driver === 'pgsql' && $stats['vector_database'] === 'pgvector' && $totalEmbeddings > 10000) {
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Optimize pgvector index',
                'description' => 'Consider HNSW index for better performance with large datasets.',
                'action' => 'CREATE INDEX ON embeddings USING hnsw (embedding_vector vector_cosine_ops);',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'info',
                'title' => 'System optimized',
                'description' => 'Your vector database setup is appropriate for your current scale.',
            ];
        }

        return response()->json([
            'current_setup' => [
                'database' => $driver,
                'vector_storage' => $stats['vector_database'],
                'total_embeddings' => $totalEmbeddings,
            ],
            'recommendations' => $recommendations,
        ]);
    }
}
