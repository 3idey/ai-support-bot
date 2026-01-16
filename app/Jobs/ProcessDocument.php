<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

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

            // 1. Clean up existing data for idempotency
            \Illuminate\Support\Facades\DB::transaction(function () {
                DocumentChunk::where('document_id', $this->document->id)->delete();
            });

            // 2. Extract Text
            $filePath = $this->document->file_path;
            $rawText = app('textExtractor')->extract(
                \Illuminate\Support\Facades\Storage::path($filePath)
            );

            if (empty(trim($rawText))) {
                throw new \Exception('No text could be extracted from the file.');
            }

            // 3. Chunk Text
            $chunks = app('chunker')->chunk($rawText, 800);

            if (empty($chunks)) {
                $this->document->update(['status' => 'completed', 'processed' => true, 'chunk_count' => 0]);

                return;
            }

            // 4. Save Chunks to DB
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

            // We use a transaction here to ensure all chunks are saved before we try to process them
            \Illuminate\Support\Facades\DB::transaction(function () use ($docChunksData) {
                foreach (array_chunk($docChunksData, 100) as $batch) {
                    DocumentChunk::insert($batch);
                }
            });

            // 5. Retrieve Chunk IDs to dispatch jobs
            $chunkIds = DocumentChunk::where('document_id', $this->document->id)
                ->orderBy('chunk_index')
                ->pluck('id');

            // 6. Create Batch Jobs (e.g., 20 chunks per job)
            $jobs = [];
            foreach ($chunkIds->chunk(20) as $chunkBatch) {
                $jobs[] = new EmbedDocumentChunks($chunkBatch->all());
            }

            $document = $this->document;

            // 7. Dispatch Batch
            Bus::batch($jobs)
                ->name('Process Document: '.$document->id)
                ->allowFailures()
                ->then(function (\Illuminate\Bus\Batch $batch) use ($document, $chunks) {
                    // Update Document on success
                    $document->update([
                        'status' => 'completed',
                        'processed' => true,
                        'chunk_count' => count($chunks),
                    ]);
                })
                ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($document) {
                    // Update Document on failure
                    $document->update([
                        'status' => 'failed',
                        'error_message' => 'Batch failed: '.$e->getMessage(),
                    ]);
                })
                ->finally(function (\Illuminate\Bus\Batch $batch) {
                    // Optional cleanup
                })
                ->dispatch();

        } catch (\Throwable $e) {
            $this->document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
