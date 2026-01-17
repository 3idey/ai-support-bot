<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChatService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
        protected RetrievalService $retrievalService
    ) {
    }

    public function processQuery(?User $user, array $data): array
    {
        $conversation = $this->resolveConversation($user, $data);
        $question = $data['question'];

        $this->saveUserMessage($conversation, $question);

        $queryEmbedding = $this->embeddingService->embed($question);
        $chunks = $this->retrievalService->getRelevantChunks($queryEmbedding, 5, $conversation->workspace_id);
        $context = $this->buildContext($chunks);
        $messages = $this->buildMessages($conversation, $context, $question);

        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'chunks' => $chunks,
            'context' => $context,
        ];
    }

    protected function resolveConversation(?User $user, array $data): Conversation
    {
        $conversationId = $data['conversation_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;

        if ($conversationId) {
            $conversation = Conversation::findOrFail($conversationId);

            if ($user && $conversation->user_id !== $user->id) {
                // Using AuthorizationException is more appropriate for services than abort()
                throw new AuthorizationException('Unauthorized access to this conversation.');
            }
        } else {
            $conversation = Conversation::create([
                'user_id' => $user?->id ?? 1,
                'workspace_id' => $workspaceId,
            ]);
        }

        return $conversation;
    }

    protected function saveUserMessage(Conversation $conversation, string $question): void
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);
    }

    protected function buildContext(array $chunks): string
    {
        return collect($chunks)
            ->pluck('content')
            ->implode("\n\n");
    }

    protected function buildMessages(Conversation $conversation, string $context, string $question): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => "You are an AI support assistant. Use the following context to answer the user's question. If the answer is not in the context, say so.\n\nContext:\n" . $context,
            ],
        ];

        $history = $conversation->messages()
            ->latest()
            ->take(5)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        return $messages;
    }
}
