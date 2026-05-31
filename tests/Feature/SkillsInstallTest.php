<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('install without token returns 401', function () {
    $this->getJson('/api/skills/install')->assertUnauthorized();
});

test('install with token lacking ingest ability returns 403', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/skills/install')->assertForbidden();
});

test('install returns shell script for claude_code', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $response = $this->withToken($token)->get('/api/skills/install?agent=claude_code');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/x-shellscript');

    $body = $response->getContent();
    expect($body)
        ->toContain('#!/bin/bash')
        ->toContain('.claude/skills')
        ->toContain('YOMITOKI_TOKEN=')
        ->toContain('YOMITOKI_URL=');
});

test('install script saves quoted config values', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $body = $this->withToken($token)->get('/api/skills/install')->getContent();

    expect($body)
        ->toContain("YOMITOKI_URL='%s'")
        ->toContain("YOMITOKI_TOKEN='%s'");
});

test('install returns shell script for codex', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $response = $this->withToken($token)->get('/api/skills/install?agent=codex');

    $response->assertOk();
    $body = $response->getContent();
    expect($body)->toContain('.agents/skills');
});

test('install defaults to all agents', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $response = $this->withToken($token)->get('/api/skills/install');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('.claude/skills')
        ->toContain('.agents/skills');
});

test('install with agent=all includes both skill directories', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $response = $this->withToken($token)->get('/api/skills/install?agent=all');

    $response->assertOk();
    expect($response->getContent())
        ->toContain('.claude/skills')
        ->toContain('.agents/skills');
});

test('install with invalid agent returns 422', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->getJson('/api/skills/install?agent=unknown')->assertUnprocessable();
});

test('install with invalid profile returns 422', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $this->withToken($token)->getJson('/api/skills/install?profile=unknown')->assertUnprocessable();
});

// --- profile ---

test('profile=full includes all distributable skills', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $body = $this->withToken($token)->get('/api/skills/install?profile=full')->getContent();

    expect($body)
        ->toContain('scrap-utils')
        ->toContain('plan-to-markdown')
        ->toContain('execution-result');
});

test('default profile includes all distributable skills', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $body = $this->withToken($token)->get('/api/skills/install')->getContent();

    expect($body)
        ->toContain('scrap-utils')
        ->toContain('plan-to-markdown')
        ->toContain('execution-result');
});

test('profile=basic includes only scrap-utils', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $body = $this->withToken($token)->get('/api/skills/install?profile=basic')->getContent();

    expect($body)->toContain('# Skill: scrap-utils');
    expect($body)->not->toContain('# Skill: plan-to-markdown');
    expect($body)->not->toContain('# Skill: execution-result');
});

test('profile=plan includes plan-to-markdown and execution-result only', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    $body = $this->withToken($token)->get('/api/skills/install?profile=plan')->getContent();

    expect($body)->toContain('# Skill: plan-to-markdown');
    expect($body)->toContain('# Skill: execution-result');
    expect($body)->not->toContain('# Skill: scrap-utils');
});

test('internal skills are never included in any profile', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['ingest'])->plainTextToken;

    foreach (['basic', 'plan', 'full'] as $profile) {
        $body = $this->withToken($token)->get("/api/skills/install?profile=$profile")->getContent();

        expect($body)->not->toContain('# Skill: laravel-best-practices');
        expect($body)->not->toContain('# Skill: fortify-development');
        expect($body)->not->toContain('# Skill: pest-testing');
        expect($body)->not->toContain('# Skill: wayfinder-development');
        expect($body)->not->toContain('# Skill: playwright-cli');
    }
});
