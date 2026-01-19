<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class ChatResponseService
{
    private const AI_MODEL = 'gpt-4o-mini';

    private const STREAM_HEADERS = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ];

    public function stream(array $messages, $conversation, $chunks, string $context)
    {
        return response()->stream(
            fn () => $this->handleStreamResponse($messages, $conversation, $chunks, $context),
            200,
            self::STREAM_HEADERS
        );
    }

    private function handleStreamResponse(array $messages, $conversation, $chunks, string $context): void
    {
        $this->sendInitialStreamData($chunks, $conversation->id);

        $fullAnswer = '';
        $isError = false;

        try {
            $fullAnswer = $this->streamAiResponse($messages);
        } catch (\Exception $e) {
            $isError = true;
            $this->handleStreamError($e, $context);
        }

        if (! $isError || app()->environment('local')) {
            $this->finalizeStream($conversation, $fullAnswer, $chunks);
        }
    }

    private function sendInitialStreamData(array $chunks, int $conversationId): void
    {
        echo "event: sources\n";
        echo 'data: '.json_encode($chunks)."\n\n";

        echo "event: conversation_id\n";
        echo 'data: '.json_encode(['id' => $conversationId])."\n\n";

        $this->flushOutput();
    }

    private function streamAiResponse(array $messages): string
    {
        $fullAnswer = '';

        $stream = OpenAI::chat()->createStreamed([
            'model' => self::AI_MODEL,
            'messages' => $messages,
        ]);

        foreach ($stream as $response) {
            $text = $response->choices[0]->delta->content ?? '';
            if (strlen($text) > 0) {
                $fullAnswer .= $text;
                echo 'data: '.json_encode(['content' => $text])."\n\n";
                $this->flushOutput();
            }
        }

        return $fullAnswer;
    }

    private function finalizeStream($conversation, string $fullAnswer, array $chunks): void
    {
        echo "data: [DONE]\n\n";
        $this->flushOutput();

        $this->saveAssistantMessage($conversation, $fullAnswer, $chunks);
    }

    private function flushOutput(): void
    {
        ob_flush();
        flush();
    }

    public function json(array $messages, $conversation, $chunks, string $context)
    {
        try {
            $answer = $this->getAiResponse($messages);
            $this->saveAssistantMessage($conversation, $answer, $chunks);

            return response()->json([
                'answer' => $answer,
                'sources' => $chunks,
                'conversation_id' => $conversation->id,
            ]);
        } catch (\Exception $e) {
            return $this->handleJsonError($e, $conversation, $chunks, $context);
        }
    }

    private function getAiResponse(array $messages): string
    {
        $response = OpenAI::chat()->create([
            'model' => self::AI_MODEL,
            'messages' => $messages,
        ]);

        return $response['choices'][0]['message']['content'];
    }

    private function saveAssistantMessage($conversation, string $content, array $chunks): void
    {
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'sources' => $chunks,
        ]);
    }

    private function handleJsonError(\Exception $e, $conversation, array $chunks, string $context)
    {
        if (app()->environment('local')) {
            $fallbackAnswer = $this->buildFallbackAnswer($context);
            $this->saveAssistantMessage($conversation, $fallbackAnswer, $chunks);

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

    private function buildFallbackAnswer(string $context): string
    {
        return "I'm sorry, I'm having trouble connecting to the AI service. Here is what I found in the documents: \n\n".$context;
    }

    private function handleStreamError(\Exception $e, string $context): void
    {
        if (app()->environment('local')) {
            $this->streamFallbackAnswer($context);
        } else {
            $this->streamErrorMessage();
        }
    }

    private function streamFallbackAnswer(string $context): void
    {
        $fallbackAnswer = "I'm sorry, I'm having trouble connecting to the AI service (Rate limit or connection issue). Here is what I found: \n\n".$context;

        foreach (explode(' ', $fallbackAnswer) as $word) {
            echo 'data: '.json_encode(['content' => $word.' '])."\n\n";
            $this->flushOutput();
            usleep(50000);
        }
    }

    private function streamErrorMessage(): void
    {
        echo "event: error\n";
        echo 'data: '.json_encode(['message' => 'AI Service Unavailable'])."\n\n";
    }
}
