<?php

namespace App\Http\Controllers;

use App\Services\RetrievalService;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    /**
     * Handle the chat question request.
     */
    public function ask(Request $request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'question' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
            'workspace_id' => 'required_without:conversation_id|exists:workspaces,id',
        ]);

        $user = $request->user() ?: \App\Models\User::first();
        $conversation = $this->resolveConversation($request, $user);
        $question = $request->question;

        $queryEmbedding = $this->embedQuestion($question);
        $chunks = $this->retrieveRelevantChunks($queryEmbedding);
        $context = $this->buildContext($chunks);
        $messages = $this->buildMessages($conversation, $context, $question);

        if ($request->boolean('stream')) {
            return $this->handleStreamResponse($messages, $conversation, $chunks, $context);
        }

        return $this->handleJsonResponse($messages, $conversation, $chunks, $context);
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

    private function embedQuestion(string $question)
    {
        $embeddingService = new \App\Services\EmbeddingService;
        return $embeddingService->embed($question);
    }

    private function retrieveRelevantChunks($queryEmbedding)
    {
        $retrieval = new RetrievalService;
        return $retrieval->getRelevantChunks($queryEmbedding);
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

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        return $messages;
    }

    private function handleStreamResponse($messages, $conversation, $chunks, $context)
    {
        return response()->stream(function () use ($messages, $conversation, $chunks, $context) {
            echo "event: sources\n";
            echo 'data: ' . json_encode($chunks) . "\n\n";

            echo "event: conversation_id\n";
            echo 'data: ' . json_encode(['id' => $conversation->id]) . "\n\n";

            ob_flush();
            flush();

            $fullAnswer = '';
            $isError = false;

            try {
                $stream = OpenAI::chat()->createStreamed([
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                ]);

                foreach ($stream as $response) {
                    $text = $response->choices[0]->delta->content;
                    if (strlen($text) > 0) {
                        $fullAnswer .= $text;
                        echo 'data: ' . json_encode(['content' => $text]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
            } catch (\Exception $e) {
                $isError = true;
                $this->handleStreamError($e, $context);
            }

            if (!$isError || app()->environment('local')) {
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();

                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $fullAnswer,
                    'sources' => $chunks,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function handleStreamError(\Exception $e, string $context)
    {
        if (app()->environment('local')) {
            $fallbackAnswer = "I'm sorry, I'm having trouble connecting to the AI service (Rate limit or connection issue). Here is what I found: \n\n" . $context;

            foreach (explode(' ', $fallbackAnswer) as $word) {
                echo 'data: ' . json_encode(['content' => $word . ' ']) . "\n\n";
                ob_flush();
                flush();
                usleep(50000);
            }
        } else {
            echo "event: error\n";
            echo 'data: ' . json_encode(['message' => 'AI Service Unavailable']) . "\n\n";
        }
    }

    private function handleJsonResponse($messages, $conversation, $chunks, $context)
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
            ]);

            $answer = $response['choices'][0]['message']['content'];

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $answer,
                'sources' => $chunks,
            ]);

            return response()->json([
                'answer' => $answer,
                'sources' => $chunks,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                $fallbackAnswer = "I'm sorry, I'm having trouble connecting to the AI service. Here is what I found in the documents: \n\n" . $context;

                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $fallbackAnswer,
                    'sources' => $chunks,
                ]);

                return response()->json([
                    'answer' => $fallbackAnswer,
                    'sources' => $chunks,
                    'error' => $e->getMessage(),
                    'debug' => 'Local fallback triggered',
                    'conversation_id' => $conversation->id,
                ]);
            }
            throw $e;
        }
    }
}
