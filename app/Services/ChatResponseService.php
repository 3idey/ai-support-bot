<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class ChatResponseService
{
    public function stream(array $messages, $conversation, $chunks, string $context)
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

    public function json(array $messages, $conversation, $chunks, string $context)
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
}
