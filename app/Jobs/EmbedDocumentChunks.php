<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\Embedding;
use App\Services\EmbeddingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmbedDocumentChunks implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $chunkIds
     */
    public function __construct(public array $chunkIds) {}

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $chunks = DocumentChunk::whereIn('id', $this->chunkIds)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $contents = $chunks->pluck('content')->all();
        $vectors = $embeddingService->embed($contents);

        $now = now();
        $embeddingsData = [];

        foreach ($chunks as $index => $chunk) {
            // vectors array aligns with chunks collection order because we ordered by chunk_index
            // and the input to embed() was also ordered by chunk_index (implicitly via pluck)
            if (! isset($vectors[$index])) {
                continue;
            }

            $embeddingsData[] = [
                'document_chunk_id' => $chunk->id,
                'embedding' => json_encode($vectors[$index]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Embedding::insert($embeddingsData);
    }
}
