<?php

use App\Jobs\EmbedDocumentChunks;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('it creates embeddings for given chunks', function () {
    // 1. Setup Data
    $document = Document::factory()->create();
    $chunks = DocumentChunk::factory()->count(3)->create([
        'document_id' => $document->id,
    ]);

    // 2. Mock Embedding Service
    $this->mock(EmbeddingService::class, function (MockInterface $mock) {
        $mock->shouldReceive('embed')
            ->once()
            ->andReturn([
                array_fill(0, 1536, 0.1),
                array_fill(0, 1536, 0.2),
                array_fill(0, 1536, 0.3),
            ]);
    });

    // 3. Run Job
    $job = new EmbedDocumentChunks($chunks->pluck('id')->toArray());
    $job->handle(app(EmbeddingService::class));

    // 4. Assertions
    $this->assertDatabaseCount('embeddings', 3);

    foreach ($chunks as $chunk) {
        $this->assertDatabaseHas('embeddings', [
            'document_chunk_id' => $chunk->id,
        ]);
    }
});
