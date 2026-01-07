<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Jobs\ProcessDocument;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'workspace_id' => 'required',
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:2048',
        ]);

        $file = $request->file('file');
        $path = $file->store("workspace-docs/{$request->workspace_id}");

        $document = Document::create([
            'workspace_id' => $request->workspace_id,
            'title' => $file->getClientOriginalName(),
            'source_type' => 'file',
            'file_path' => $path,
            'processed' => false,
        ]);
        // queue stuff to process document 
        dispatch(new ProcessDocument($document));

        return response()->json([
            'message' => 'Document uploaded successfully — processing started...',
            'document' => $document
        ]);
    }
}
