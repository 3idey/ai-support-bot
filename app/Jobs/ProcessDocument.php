<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Services\EmbeddingService;
use App\Models\Embedding;

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

            $filePath = $this->document->file_path;

            $rawText = app('textExtractor')->extract(
                Storage::path($filePath)
            );

            if (empty(trim($rawText))) {
                throw new \Exception("No text could be extracted from the file.");
            }

            $chunks = app('chunker')->chunk($rawText, 800);
            $embeddingService = new EmbeddingService();

            $docChunks = [];
            foreach ($chunks as $i => $chunk) {
                $docChunks[] = DocumentChunk::create([
                    'document_id' => $this->document->id,
                    'content' => $chunk,
                    'chunk_index' => $i,
                ]);
            }

            if (!empty($chunks)) {
                $vectors = $embeddingService->embed($chunks);

                foreach ($docChunks as $index => $docChunk) {
                    Embedding::create([
                        'document_chunk_id' => $docChunk->id,
                        'embedding' => $vectors[$index],
                    ]);
                }
            }

            $this->document->update([
                'chunk_count' => count($chunks),
                'processed' => true,
                'status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            $this->document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to ensure the job is marked as failed in the queue system
        }
    }
}
