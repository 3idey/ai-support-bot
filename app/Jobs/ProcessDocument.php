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

            $this->cleanupExistingData();

            $rawText = $this->extractText();
            $chunks = $this->chunkText($rawText);

            if (empty($chunks)) {
                $this->markAsCompleted(0);
                return;
            }

            $docChunksData = $this->prepareChunksData($chunks);
            $this->saveChunks($docChunksData);

            $this->dispatchBatch($chunks);

        } catch (\Throwable $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    private function cleanupExistingData(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            DocumentChunk::where('document_id', $this->document->id)->delete();
        });
    }

    private function extractText(): string
    {
        $filePath = $this->document->file_path;
        $rawText = app('textExtractor')->extract(
            \Illuminate\Support\Facades\Storage::path($filePath)
        );

        if (empty(trim($rawText))) {
            throw new \Exception('No text could be extracted from the file.');
        }

        return $rawText;
    }

    private function chunkText(string $rawText): array
    {
        return app('chunker')->chunk($rawText, 800);
    }

    private function markAsCompleted(int $chunkCount): void
    {
        $this->document->update([
            'status' => 'completed',
            'processed' => true,
            'chunk_count' => $chunkCount
        ]);
    }

    private function prepareChunksData(array $chunks): array
    {
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
        return $docChunksData;
    }

    private function saveChunks(array $docChunksData): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($docChunksData) {
            foreach (array_chunk($docChunksData, 100) as $batch) {
                DocumentChunk::insert($batch);
            }
        });
    }

    private function dispatchBatch(array $chunks): void
    {
        $chunkIds = DocumentChunk::where('document_id', $this->document->id)
            ->orderBy('chunk_index')
            ->pluck('id');

        $jobs = [];
        foreach ($chunkIds->chunk(20) as $chunkBatch) {
            $jobs[] = new EmbedDocumentChunks($chunkBatch->all());
        }

        $document = $this->document;

        Bus::batch($jobs)
            ->name('Process Document: ' . $document->id)
            ->allowFailures()
            ->then(function (\Illuminate\Bus\Batch $batch) use ($document, $chunks) {
                $document->update([
                    'status' => 'completed',
                    'processed' => true,
                    'chunk_count' => count($chunks),
                ]);
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($document) {
                $document->update([
                    'status' => 'failed',
                    'error_message' => 'Batch failed: ' . $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    private function handleFailure(\Throwable $e): void
    {
        $this->document->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
