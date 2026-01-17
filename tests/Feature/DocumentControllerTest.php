<?php

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('user can upload a document', function () {
    Storage::fake('local');
    Queue::fake();

    $workspace = Workspace::factory()->create();

    $file = UploadedFile::fake()->create('document.pdf');

    $response = $this->postJson('/documents/upload', [
        'workspace_id' => $workspace->id,
        'file' => $file,
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Document uploaded successfully — processing started...']);

    $this->assertDatabaseHas('documents', [
        'title' => 'document.pdf',
        'workspace_id' => $workspace->id,
    ]);

    Queue::assertPushed(ProcessDocument::class);
});
