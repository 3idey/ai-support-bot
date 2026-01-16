<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Embedding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // Wait 1 minute before retrying

    /**
     * Create a new job instance.
     */
    public Document $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->document->update(['status' => 'processing']);

            \Illuminate\Support\Facades\DB::transaction(function () {
                // Delete existing chunks and embeddings for this document to ensure idempotency
                $existingChunkIds = DocumentChunk::where('document_id', $this->document->id)
                    ->pluck('id');

                if ($existingChunkIds->isNotEmpty()) {
                    Embedding::whereIn('document_chunk_id', $existingChunkIds)->delete();
                    DocumentChunk::where('document_id', $this->document->id)->delete();
                }

                $filePath = $this->document->file_path;
                $rawText = app('textExtractor')->extract(
                    \Illuminate\Support\Facades\Storage::path($filePath)
                );

                if (empty(trim($rawText))) {
                    throw new \Exception('No text could be extracted from the file.');
                }

                $chunks = app('chunker')->chunk($rawText, 800);
                $embeddingService = new \App\Services\EmbeddingService;

                if (! empty($chunks)) {
                    $now = now();
                    $docChunksData = [];

                    foreach ($chunks as $i => $chunk) {
                        $docChunksData[] = [
                            'document_id' => $this->document->id,
                            'content' => $chunk,
                            'chunk_index' => $i,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // Batch insert chunks
                    DocumentChunk::insert($docChunksData);

                    // Fetch back to get IDs
                    $docChunks = DocumentChunk::where('document_id', $this->document->id)
                        ->orderBy('chunk_index')
                        ->get();

                    $vectors = $embeddingService->embed($chunks);

                    $embeddingsData = [];
                    foreach ($docChunks as $index => $docChunk) {
                        $embeddingsData[] = [
                            'document_chunk_id' => $docChunk->id,
                            'embedding' => json_encode($vectors[$index]),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    // Batch insert embeddings
                    Embedding::insert($embeddingsData);
                }

                $this->document->update([
                    'chunk_count' => count($chunks),
                    'processed' => true,
                    'status' => 'completed',
                ]);
            });
        } catch (\Throwable $e) {
            $this->document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to ensure the job is marked as failed in the queue system
        }
    }
}
