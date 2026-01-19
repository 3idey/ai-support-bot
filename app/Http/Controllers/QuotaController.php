<?php

namespace App\Http\Controllers;

use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    /**
     * Get quota status for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasQuota = $this->quotaService->checkQuota($user);
        $remaining = $this->quotaService->getRemainingQuota($user);
        $tokensUsed = $this->quotaService->getTodayTokenUsage($user);
        $messagesPerDay = config('quota.messages_per_day', 100);

        return response()->json([
            'quota' => [
                'available' => $hasQuota,
                'messages_used_today' => $messagesPerDay - $remaining,
                'messages_remaining' => $remaining,
                'messages_per_day' => $messagesPerDay,
                'tokens_used_today' => $tokensUsed,
            ],
            'limits' => [
                'messages_per_day' => $messagesPerDay,
                'documents_per_workspace' => config('quota.documents_per_workspace', 50),
                'max_document_size_kb' => config('quota.max_document_size_kb', 5120),
            ],
        ]);
    }

    /**
     * Get remaining quota count
     */
    public function remaining(Request $request): JsonResponse
    {
        $user = $request->user();
        $remaining = $this->quotaService->getRemainingQuota($user);

        return response()->json([
            'remaining' => $remaining,
        ]);
    }

    /**
     * Get token usage for today
     */
    public function tokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokensUsed = $this->quotaService->getTodayTokenUsage($user);

        return response()->json([
            'tokens_used_today' => $tokensUsed,
        ]);
    }

    /**
     * Quick boolean check if user has quota available
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasQuota = $this->quotaService->checkQuota($user);

        return response()->json([
            'has_quota' => $hasQuota,
        ]);
    }

    /**
     * Track token usage (typically called after AI response)
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'tokens' => 'required|integer|min:1|max:1000000',
        ]);

        $user = $request->user();
        $this->quotaService->trackTokenUsage($user, $request->input('tokens'));

        return response()->json([
            'success' => true,
            'message' => 'Token usage tracked successfully',
            'total_tokens_today' => $this->quotaService->getTodayTokenUsage($user),
        ]);
    }

    /**
     * Get detailed usage breakdown
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $messagesPerDay = config('quota.messages_per_day', 100);
        $remaining = $this->quotaService->getRemainingQuota($user);
        $messagesUsed = $messagesPerDay - $remaining;

        return response()->json([
            'messages' => [
                'used' => $messagesUsed,
                'remaining' => $remaining,
                'limit' => $messagesPerDay,
                'percentage_used' => $messagesPerDay > 0 ? round(($messagesUsed / $messagesPerDay) * 100, 2) : 0,
            ],
            'tokens' => [
                'used_today' => $this->quotaService->getTodayTokenUsage($user),
            ],
        ]);
    }

    /**
     * Check document quota for a workspace
     */
    public function documentsQuota(Request $request, int $workspaceId): JsonResponse
    {
        $workspace = \App\Models\Workspace::findOrFail($workspaceId);
        $currentCount = $workspace->documents()->count();
        $maxDocuments = config('quota.documents_per_workspace', 50);
        $hasQuota = $this->quotaService->checkDocumentQuota($workspaceId, $currentCount);

        return response()->json([
            'workspace_id' => $workspaceId,
            'documents' => [
                'current' => $currentCount,
                'limit' => $maxDocuments,
                'remaining' => max(0, $maxDocuments - $currentCount),
                'can_add' => $hasQuota,
            ],
        ]);
    }

    /**
     * Validate document size before upload
     */
    public function validateDocumentSize(Request $request): JsonResponse
    {
        $request->validate([
            'size_kb' => 'required|integer|min:1',
        ]);

        $sizeKb = $request->input('size_kb');
        $maxSize = config('quota.max_document_size_kb', 5120);
        $isValid = $this->quotaService->checkDocumentSize($sizeKb);

        return response()->json([
            'valid' => $isValid,
            'size_kb' => $sizeKb,
            'max_size_kb' => $maxSize,
            'message' => $isValid
                ? 'Document size is within limits'
                : "Document size exceeds the maximum allowed size of {$maxSize} KB",
        ], $isValid ? 200 : 422);
    }
}
