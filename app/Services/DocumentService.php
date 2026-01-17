<?php

namespace App\Services;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

class DocumentService
{
    public function upload(UploadedFile $file, $workspaceId): Document
    {
        $path = $file->store("workspace-docs/{$workspaceId}");

        $document = Document::create([
            'workspace_id' => $workspaceId,
            'title' => $file->getClientOriginalName(),
            'source_type' => 'file',
            'file_path' => $path,
            'processed' => false,
        ]);

        dispatch(new ProcessDocument($document));

        return $document;
    }
}
