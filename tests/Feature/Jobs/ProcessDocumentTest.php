<?php

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('it extracts text chunks and dispatches embedding batch', function () {
    // 1. Setup
    Bus::fake();
    Storage::fake('local');

    $document = Document::factory()->create([
        'file_path' => 'test.pdf',
    ]);

    // Create dummy file
    Storage::put('test.pdf', 'dummy content');

    // 2. Mock Services via Container Binding
    // We mocked 'textExtractor' and 'chunker' in the Job using app('...').
    // We can mock instances in the service container.

    $mockExtractor = Mockery::mock();
    $mockExtractor->shouldReceive('extract')->andReturn("Line 1\nLine 2\nLine 3");
    $this->swap('textExtractor', $mockExtractor);

    $mockChunker = Mockery::mock();
    $mockChunker->shouldReceive('chunk')->andReturn(['Line 1', 'Line 2', 'Line 3']);
    $this->swap('chunker', $mockChunker);

    // 3. Dispatch Job
    (new ProcessDocument($document))->handle();

    // 4. Assertions
    // Check Document status
    $document->refresh();
    expect($document->status)->toBe('processing');

    // Check Chunks in DB
    $this->assertDatabaseCount('document_chunks', 3);

    // Check Batch Dispatch
    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->name == 'Process Document: '.Document::first()->id &&
            $batch->jobs->count() === 1; // 3 chunks fit in 1 job (limit 20)
    });
});
