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

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $filePath = $this->document->file_path;

        $rawText = app('textExtractor')->extract(
            Storage::path($filePath)
        );

        $chunks = app('chunker')->chunk($rawText, 800);

        foreach ($chunks as $i => $chunk) {
            DocumentChunk::create([
                'document_id' => $this->document->id,
                'content' => $chunk,
                'chunk_index' => $i,
            ]);
        }

        $this->document->update([
            'chunk_count' => count($chunks),
            'processed' => true,
        ]);
    }
}
