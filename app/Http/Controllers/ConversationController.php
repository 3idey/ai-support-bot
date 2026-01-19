<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * List all conversations for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with('workspace')
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Get a specific conversation with messages
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::with(['workspace', 'user'])
            ->withCount('messages')
            ->findOrFail($id);

        // Check if user has access to this conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        // Check if user has access to this conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Conversation deleted successfully',
        ]);
    }

    /**
     * Get messages for a conversation
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        // Check if user has access to this conversation
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized access to this conversation',
            ], 403);
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'messages' => $messages,
        ]);
    }
}
