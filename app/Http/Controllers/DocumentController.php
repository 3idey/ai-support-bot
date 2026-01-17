<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DocumentService;

class DocumentController extends Controller
{
    public function __construct(protected DocumentService $documentService)
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required',
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:2048',
        ]);

        $document = $this->documentService->upload(
            $request->file('file'),
            $request->workspace_id
        );

        return response()->json([
            'message' => 'Document uploaded successfully — processing started...',
            'document' => $document
        ]);
    }
}
