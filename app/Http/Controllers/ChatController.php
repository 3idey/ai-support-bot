<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskQuestionRequest;
use App\Services\ChatResponseService;
use App\Services\EmbeddingService;
use App\Services\RetrievalService;
use Illuminate\Http\Request;


class ChatController extends Controller
{
    public function __construct(
        protected RetrievalService $retrievalService,
        protected EmbeddingService $embeddingService,
        protected ChatResponseService $responseService
    ) {
    }

    /**
     * Handle the chat question request.
     */
    public function ask(AskQuestionRequest $request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user() ?: \App\Models\User::first();
        $conversation = $this->resolveConversation($request, $user);
        $question = $request->validated('question');

        $this->saveUserMessage($conversation, $question);

        $queryEmbedding = $this->embedQuestion($question);
        $chunks = $this->retrieveRelevantChunks($queryEmbedding);
        $context = $this->buildContext($chunks);
        $messages = $this->buildMessages($conversation, $context, $question);

        if ($request->boolean('stream')) {
            return $this->responseService->stream($messages, $conversation, $chunks, $context);
        }

        return $this->responseService->json($messages, $conversation, $chunks, $context);
    }

    private function resolveConversation(Request $request, $user)
    {
        if ($request->conversation_id) {
            $conversation = \App\Models\Conversation::findOrFail($request->conversation_id);

            if ($user && $conversation->user_id !== $user->id) {
                abort(403, 'Unauthorized access to this conversation.');
            }
        } else {
            $conversation = \App\Models\Conversation::create([
                'user_id' => $user?->id ?? 1,
                'workspace_id' => $request->workspace_id,
            ]);
        }

        return $conversation;
    }

    private function saveUserMessage($conversation, string $question)
    {
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);
    }

    private function embedQuestion(string $question)
    {
        return $this->embeddingService->embed($question);
    }

    private function retrieveRelevantChunks($queryEmbedding)
    {
        return $this->retrievalService->getRelevantChunks($queryEmbedding);
    }

    private function buildContext($chunks)
    {
        return collect($chunks)
            ->pluck('content')
            ->implode("\n\n");
    }

    private function buildMessages($conversation, string $context, string $question)
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
