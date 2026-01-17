<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;

uses(RefreshDatabase::class);

test('user can ask a question', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    OpenAI::fake([
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ]),
        \OpenAI\Responses\Chat\CreateResponse::fake([
            'choices' => [['message' => ['content' => 'Test answer', 'role' => 'assistant']]],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/ask', [
            'question' => 'Hello?',
            'workspace_id' => $workspace->id,
        ]);

    $response->assertOk()
        ->assertJsonFragment(['answer' => 'Test answer']);

    $this->assertDatabaseHas('messages', [
        'content' => 'Hello?',
        'role' => 'user',
    ]);

    $this->assertDatabaseHas('messages', [
        'content' => 'Test answer',
        'role' => 'assistant',
    ]);
});

test('user can ask a question with streaming', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    OpenAI::fake([
        \OpenAI\Responses\Embeddings\CreateResponse::fake([
            'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
        ]),
        \OpenAI\Responses\Chat\CreateStreamedResponse::fake(),
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/ask', [
            'question' => 'Hello?',
            'workspace_id' => $workspace->id,
            'stream' => true,
        ]);

    $response->assertOk();

    // Check if it's a streamed response
    $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response->baseResponse);

    // Trigger the stream
    $response->sendContent();

    $this->assertDatabaseHas('messages', [
        'role' => 'assistant',
        'content' => 'Hello! This is a fake chat response.',
    ]);
});
