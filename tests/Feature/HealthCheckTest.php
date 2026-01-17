<?php

test('it returns health status', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'redis' => 'ok',
        ]);
});
