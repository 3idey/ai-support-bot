<?php

namespace App\Services;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    private const STORAGE_PATH_PREFIX = 'workspace-docs';

    /**
     * Upload and queue a document for processing
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  int  $workspaceId  The workspace ID to associate the document with
     * @return Document The created document model
     */
    public function upload(UploadedFile $file, int $workspaceId): Document
    {
        $path = $this->storeFile($file, $workspaceId);
        $document = $this->createDocumentRecord($file, $workspaceId, $path);
        $this->queueProcessing($document);

        return $document;
    }

    /**
     * Store uploaded file
     */
    private function storeFile(UploadedFile $file, int $workspaceId): string
    {
        return $file->store(self::STORAGE_PATH_PREFIX."/{$workspaceId}");
    }

    /**
     * Create document database record
     */
    private function createDocumentRecord(UploadedFile $file, int $workspaceId, string $path): Document
    {
        return Document::create([
            'workspace_id' => $workspaceId,
            'title' => $file->getClientOriginalName(),
            'source_type' => 'file',
            'file_path' => $path,
            'processed' => false,
        ]);
    }

    /**
     * Queue document for processing
     */
    private function queueProcessing(Document $document): void
    {
        dispatch(new ProcessDocument($document));
    }
}
