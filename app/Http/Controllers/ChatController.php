<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\EmbeddingService;
use App\Services\RetrievalService;
class ChatController extends Controller
{

    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
            'workspace_id' => 'required_without:conversation_id|exists:workspaces,id',
        ]);

        $user = $request->user();

        // 1. Resolve Conversation
        if ($request->conversation_id) {
            $conversation = \App\Models\Conversation::findOrFail($request->conversation_id);

            // Security check: Ensure user owns this conversation
            if ($conversation->user_id !== $user->id) {
                abort(403, 'Unauthorized access to this conversation.');
            }
        } else {
            $conversation = \App\Models\Conversation::create([
                'user_id' => $user->id,
                'workspace_id' => $request->workspace_id,
            ]);
        }

        $question = $request->question;

        // 2. Embed question
        $embeddingService = new EmbeddingService();
        $queryEmbedding = $embeddingService->embed($question);

        // 3. Retrieve relevant chunks
        $retrieval = new RetrievalService();
        $chunks = $retrieval->getRelevantChunks($queryEmbedding);

        // 4. Build context
        $context = collect($chunks)
            ->pluck('content')
            ->implode("\n\n");

        // 5. Build Messages History
        $messages = [
            [
                'role' => 'system',
                'content' => "You are an AI support assistant. Use the following context to answer the user's question. If the answer is not in the context, say so.\n\nContext:\n" . $context,
            ]
        ];

        // Fetch last 5 messages for history
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

        // Add current user question
        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        // Save User Message to DB
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        // HANDLE STREAMING RESPONSE
        if ($request->boolean('stream')) {
            return response()->stream(function () use ($messages, $conversation, $chunks, $context) {
                // 1. send sources event
                echo "event: sources\n";
                echo "data: " . json_encode($chunks) . "\n\n";

                // send conversation id event
                echo "event: conversation_id\n";
                echo "data: " . json_encode(['id' => $conversation->id]) . "\n\n";

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
                            echo "data: " . json_encode(['content' => $text]) . "\n\n";
                            ob_flush();
                            flush();
                        }
                    }

                } catch (\Exception $e) {
                    $isError = true;
                    if (app()->environment('local')) {
                        // local fallback simulation
                        $fallbackAnswer = "I'm sorry, I'm having trouble connecting to the AI service (Rate limit or connection issue). Here is what I found: \n\n" . $context;
                        $fullAnswer = $fallbackAnswer;

                        // simulate typing
                        foreach (explode(' ', $fallbackAnswer) as $word) {
                            echo "data: " . json_encode(['content' => $word . ' ']) . "\n\n";
                            ob_flush();
                            flush();
                            usleep(50000);
                        }
                    } else {
                        echo "event: error\n";
                        echo "data: " . json_encode(['message' => 'AI Service Unavailable']) . "\n\n";
                    }
                }

                if (!$isError || app()->environment('local')) {
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();

                    // Save Assistant Message
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

        // HANDLE NORMAL JSON RESPONSE
        try {
            // 6. Ask LLM with context AND history
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
            ]);

            $answer = $response['choices'][0]['message']['content'];

            // Save Assistant Message to DB
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
                // Local Fallback
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
