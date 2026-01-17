<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskQuestionRequest;
use App\Services\ChatResponseService;
use App\Services\ChatService;
use Illuminate\Http\Request;


class ChatController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
        protected ChatResponseService $responseService
    ) {
    }

    /**
     * Handle the chat question request.
     */
    public function ask(AskQuestionRequest $request): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user() ?: \App\Models\User::first();

        $result = $this->chatService->processQuery($user, $request->validated());

        $messages = $result['messages'];
        $conversation = $result['conversation'];
        $chunks = $result['chunks'];
        $context = $result['context'];

        if ($request->boolean('stream')) {
            return $this->responseService->stream($messages, $conversation, $chunks, $context);
        }

        return $this->responseService->json($messages, $conversation, $chunks, $context);
    }


}
