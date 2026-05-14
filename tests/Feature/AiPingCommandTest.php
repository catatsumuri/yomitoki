<?php

use App\Ai\Agents\PingAgent;

test('ai ping command prints pong', function () {
    PingAgent::fake(['pong']);

    $this->artisan('ai:ping')
        ->expectsOutputToContain('Pinging AI provider [bedrock]')
        ->expectsOutputToContain('pong')
        ->assertSuccessful();
});
