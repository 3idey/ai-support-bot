<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentRequest;
use App\Services\DocumentService;

class DocumentController extends Controller
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    public function store(UploadDocumentRequest $request)
    {
        $document = $this->documentService->upload(
            $request->file('file'),
            $request->validated('workspace_id')
        );

        return response()->json([
            'message' => 'Document uploaded successfully — processing started...',
            'document' => $document
        ]);
    }
}
