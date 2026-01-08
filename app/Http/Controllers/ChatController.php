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
        ]);

        $question = $request->question;

        // 1. Embed question
        $embeddingService = new EmbeddingService();
        $queryEmbedding = $embeddingService->embed($question);

        // 2. Retrieve relevant chunks
        $retrieval = new RetrievalService();
        $chunks = $retrieval->getRelevantChunks($queryEmbedding);

        // 3. Build context
        $context = collect($chunks)
            ->pluck('content')
            ->implode("\n\n");

        try {
            // 4. Ask LLM with context
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' =>
                            "You are an AI support assistant. Use ONLY the following context:\n\n" .
                            $context,
                    ],
                    [
                        'role' => 'user',
                        'content' => $question,
                    ],
                ],
            ]);

            return response()->json([
                'answer' => $response['choices'][0]['message']['content'],
                'sources' => $chunks,
            ]);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                return response()->json([
                    'answer' => "I'm sorry, I'm having trouble connecting to the AI service (Rate limit or connection issue). Here is what I found in the documents: \n\n" . $context,
                    'sources' => $chunks,
                    'error' => $e->getMessage(),
                    'debug' => 'Local fallback triggered'
                ]);
            }
            throw $e;
        }
    }
}
