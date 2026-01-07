<?php

use App\Services\EmbeddingService;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;

it('generates embeddings for text', function () {
    OpenAI::fake([
        CreateResponse::fake([
            'data' => [
                [
                    'embedding' => [0.1, 0.2, 0.3],
                ],
            ],
        ]),
    ]);

    $service = new EmbeddingService;
    $result = $service->embed('Hello world');

    expect($result)->toBe([0.1, 0.2, 0.3]);
});
