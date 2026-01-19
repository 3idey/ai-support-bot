<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadDocumentRequest;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(protected DocumentService $documentService) {}

    /**
     * List all documents for authenticated user's workspaces
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaceId = $request->query('workspace_id');

        $query = Document::with('workspace')->withCount('chunks');

        if ($workspaceId) {
            // Verify user has access to this workspace
            $workspace = \App\Models\Workspace::findOrFail($workspaceId);
            if (!$workspace->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'message' => 'Unauthorized access to this workspace',
                ], 403);
            }
            $query->where('workspace_id', $workspaceId);
        } else {
            // Get documents from all user's workspaces
            $workspaceIds = $user->workspaces()->pluck('workspaces.id');
            $query->whereIn('workspace_id', $workspaceIds);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Upload a new document
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $document = $this->documentService->upload(
            $request->file('file'),
            $request->validated('workspace_id')
        );

        return response()->json([
            'message' => 'Document uploaded successfully — processing started...',
            'document' => $document
        ], 201);
    }

    /**
     * Get a specific document
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::with('workspace')->withCount('chunks')->findOrFail($id);

        // Verify user has access to this document's workspace
        if (!$document->workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this document',
            ], 403);
        }

        return response()->json([
            'document' => $document,
        ]);
    }

    /**
     * Delete a document
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Verify user has access to this document's workspace
        if (!$document->workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this document',
            ], 403);
        }

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully',
        ]);
    }
}
