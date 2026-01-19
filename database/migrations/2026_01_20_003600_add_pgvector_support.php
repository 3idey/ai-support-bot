<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add pgvector support
     *
     * Requirements:
     * 1. PostgreSQL 11+ with pgvector extension installed
     * 2. Run: CREATE EXTENSION IF NOT EXISTS vector;
     */
    public function up(): void
    {
        // Check if we're using PostgreSQL
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Enable pgvector extension (requires superuser or extension already created)
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

            // Add vector column (assuming OpenAI embeddings are 1536 dimensions)
            DB::statement('ALTER TABLE embeddings ADD COLUMN embedding_vector vector(1536)');

            // Create index for faster similarity search using cosine distance
            DB::statement('CREATE INDEX embeddings_embedding_vector_idx ON embeddings USING ivfflat (embedding_vector vector_cosine_ops) WITH (lists = 100)');

            // Optionally migrate existing JSON embeddings to vector format
            // DB::statement("UPDATE embeddings SET embedding_vector = embedding::text::vector WHERE embedding_vector IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS embeddings_embedding_vector_idx');
            DB::statement('ALTER TABLE embeddings DROP COLUMN IF EXISTS embedding_vector');
            // Note: We don't drop the extension as other tables might use it
        }
    }
};
