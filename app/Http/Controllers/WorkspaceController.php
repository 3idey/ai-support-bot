<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * List all workspaces for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaces = $user->workspaces()->withCount(['documents', 'conversations'])->get();

        return response()->json([
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Create a new workspace
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:workspaces,slug',
        ]);

        $workspace = Workspace::create($validated);

        // Attach current user to the workspace
        $workspace->users()->attach($request->user()->id);

        return response()->json([
            'message' => 'Workspace created successfully',
            'workspace' => $workspace,
        ], 201);
    }

    /**
     * Get a specific workspace
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::withCount(['documents', 'conversations'])
            ->findOrFail($id);

        // Check if user has access to this workspace
        if (!$workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this workspace',
            ], 403);
        }

        return response()->json([
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update a workspace
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        // Check if user has access to this workspace
        if (!$workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this workspace',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:workspaces,slug,' . $id,
        ]);

        $workspace->update($validated);

        return response()->json([
            'message' => 'Workspace updated successfully',
            'workspace' => $workspace,
        ]);
    }

    /**
     * Delete a workspace
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        // Check if user has access to this workspace
        if (!$workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this workspace',
            ], 403);
        }

        $workspace->delete();

        return response()->json([
            'message' => 'Workspace deleted successfully',
        ]);
    }

    /**
     * Get documents for a workspace
     */
    public function documents(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        // Check if user has access to this workspace
        if (!$workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this workspace',
            ], 403);
        }

        $documents = $workspace->documents()
            ->withCount('chunks')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'documents' => $documents,
        ]);
    }

    /**
     * Get conversations for a workspace
     */
    public function conversations(Request $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);

        // Check if user has access to this workspace
        if (!$workspace->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'Unauthorized access to this workspace',
            ], 403);
        }

        $conversations = $workspace->conversations()
            ->with('user')
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }
}
