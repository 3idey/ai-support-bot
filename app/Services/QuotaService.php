<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class QuotaService
{
    /**
     * Check if user has quota available for the current day
     */
    public function checkQuota(User $user): bool
    {
        $messagesPerDay = config('quota.messages_per_day', 100);
        $todayMessageCount = $this->getTodayMessageCount($user);

        return $todayMessageCount < $messagesPerDay;
    }

    /**
     * Track token usage for a user's message
     */
    public function trackTokenUsage(User $user, int $tokens): void
    {
        $cacheKey = $this->getDailyTokenCacheKey($user);
        $currentUsage = Cache::get($cacheKey, 0);
        $newUsage = $currentUsage + $tokens;

        // Store until end of day
        $expiresAt = Carbon::tomorrow();
        Cache::put($cacheKey, $newUsage, $expiresAt);
    }

    /**
     * Get remaining message quota for today
     */
    public function getRemainingQuota(User $user): int
    {
        $messagesPerDay = config('quota.messages_per_day', 100);
        $todayMessageCount = $this->getTodayMessageCount($user);

        return max(0, $messagesPerDay - $todayMessageCount);
    }

    /**
     * Get total tokens used today
     */
    public function getTodayTokenUsage(User $user): int
    {
        $cacheKey = $this->getDailyTokenCacheKey($user);
        return Cache::get($cacheKey, 0);
    }

    /**
     * Get count of messages sent today
     */
    protected function getTodayMessageCount(User $user): int
    {
        $startOfDay = Carbon::today();

        return $user->conversations()
            ->whereHas('messages', function ($query) use ($startOfDay) {
                $query->where('role', 'user')
                    ->where('created_at', '>=', $startOfDay);
            })
            ->withCount(['messages' => function ($query) use ($startOfDay) {
                $query->where('role', 'user')
                    ->where('created_at', '>=', $startOfDay);
            }])
            ->get()
            ->sum('messages_count');
    }

    /**
     * Generate cache key for daily token usage
     */
    protected function getDailyTokenCacheKey(User $user): string
    {
        $date = Carbon::today()->format('Y-m-d');
        return "quota:tokens:{$user->id}:{$date}";
    }

    /**
     * Check if workspace has reached document limit
     */
    public function checkDocumentQuota(int $workspaceId, int $currentCount): bool
    {
        $maxDocuments = config('quota.documents_per_workspace', 50);
        return $currentCount < $maxDocuments;
    }

    /**
     * Check if document size is within limits (in KB)
     */
    public function checkDocumentSize(int $sizeInKb): bool
    {
        $maxSize = config('quota.max_document_size_kb', 5120);
        return $sizeInKb <= $maxSize;
    }
}
