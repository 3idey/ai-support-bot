<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ChatService
{
    private const MAX_HISTORY_MESSAGES = 5;

    private const DEFAULT_CHUNK_LIMIT = 5;

    public function __construct(
        protected EmbeddingService $embeddingService,
        protected RetrievalService $retrievalService
    ) {}

    /**
     * Process a user query and prepare the AI conversation
     *
     * @param  User|null  $user  The authenticated user
     * @param  array  $data  Request data containing question and optional conversation_id
     * @return array Contains conversation, messages, chunks, and context
     */
    public function processQuery(?User $user, array $data): array
    {
        $conversation = $this->resolveConversation($user, $data);
        $question = $data['question'];

        $this->saveUserMessage($conversation, $question);

        $queryEmbedding = $this->embeddingService->embed($question);
        $chunks = $this->retrievalService->getRelevantChunks(
            $queryEmbedding,
            self::DEFAULT_CHUNK_LIMIT,
            $conversation->workspace_id
        );
        $context = $this->buildContext($chunks);
        $messages = $this->buildMessages($conversation, $context, $question);

        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'chunks' => $chunks,
            'context' => $context,
        ];
    }

    /**
     * Resolve or create a conversation
     *
     * @throws AuthorizationException If user tries to access another user's conversation
     */
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

    /**
     * Build message array for AI including system prompt, history, and current question
     */
    protected function buildMessages(Conversation $conversation, string $context, string $question): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        $history = $conversation->messages()
            ->latest()
            ->take(self::MAX_HISTORY_MESSAGES)
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

    /**
     * Build system prompt with context
     */
    protected function buildSystemPrompt(string $context): string
    {
        return "You are an AI support assistant. Use the following context to answer the user's question. If the answer is not in the context, say so.\n\nContext:\n".$context;
    }
}
