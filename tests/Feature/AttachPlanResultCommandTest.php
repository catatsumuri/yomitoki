<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('attaches result as child scrap to the plan', function () {
    Queue::fake();
    $user = User::factory()->create();
    $plan = Scrap::factory()->for($user)->create([
        'slug' => 'add-user-auth',
        'source_type' => 'plan',
    ]);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, "# Execution result\n\nAll done.");

    try {
        $this->artisan('plans:result', [
            '--plan' => 'add-user-auth',
            '--file' => $file,
        ])
            ->assertSuccessful();

        $result = Scrap::where('source_type', 'execution')->first();

        expect($result)->not->toBeNull()
            ->and($result->parent_id)->toBe($plan->id)
            ->and($result->source_type)->toBe('execution')
            ->and($result->status)->toBe('processed')
            ->and($result->content_markdown)->toContain('# Execution result')
            ->and($result->meta['plan_slug'])->toBe('add-user-auth')
            ->and($result->meta['created_from'])->toBe('execution-result-skill')
            ->and($result->meta['project'])->toBe('yomitoki')
            ->and($result->meta['tags'])->toBe(['yomitoki']);
    } finally {
        unlink($file);
    }
});

test('stores project tag derived from parent plan metadata', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create([
        'slug' => 'add-user-auth',
        'source_type' => 'plan',
        'meta' => ['project' => 'yomitoki', 'tags' => ['yomitoki']],
    ]);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, "# Execution result\n\nAll done.");

    try {
        $this->artisan('plans:result', [
            '--plan' => 'add-user-auth',
            '--file' => $file,
        ])->assertSuccessful();

        $result = Scrap::where('source_type', 'execution')->first();

        expect($result->meta['project'])->toBe('yomitoki')
            ->and($result->meta['tags'])->toBe(['yomitoki']);
    } finally {
        unlink($file);
    }
});

test('stores created from metadata when provided', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create([
        'slug' => 'add-user-auth',
        'source_type' => 'plan',
    ]);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, "# Execution result\n\nAll done.");

    try {
        $this->artisan('plans:result', [
            '--plan' => 'add-user-auth',
            '--file' => $file,
            '--created-from' => 'codex-execution-result-skill',
        ])->assertSuccessful();

        expect(Scrap::where('source_type', 'execution')->first()->meta['created_from'])
            ->toBe('codex-execution-result-skill');
    } finally {
        unlink($file);
    }
});

test('dispatches embedding and summary jobs after attaching', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'my-plan', 'source_type' => 'plan']);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', ['--plan' => 'my-plan', '--file' => $file])
            ->assertSuccessful();

        Queue::assertPushed(GenerateScrapEmbeddingJob::class);
        Queue::assertPushed(SummarizeScrapJob::class);
    } finally {
        unlink($file);
    }
});

test('uses provided title for the result scrap', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'my-plan', 'source_type' => 'plan']);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', [
            '--plan' => 'my-plan',
            '--file' => $file,
            '--title' => 'Custom result title',
        ])->assertSuccessful();

        expect(Scrap::where('source_type', 'execution')->first()->title)->toBe('Custom result title');
    } finally {
        unlink($file);
    }
});

test('defaults title to execution result with plan slug', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'add-auth', 'source_type' => 'plan']);

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', ['--plan' => 'add-auth', '--file' => $file])
            ->assertSuccessful();

        expect(Scrap::where('source_type', 'execution')->first()->title)
            ->toBe(__('Execution result: :plan', ['plan' => 'add-auth']));
    } finally {
        unlink($file);
    }
});

test('generates unique slug when title already exists', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'my-plan', 'source_type' => 'plan']);
    $baseSlug = Str::slug(__('Execution result: :plan', ['plan' => 'my-plan']));

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', ['--plan' => 'my-plan', '--file' => $file])
            ->assertSuccessful();

        expect(Scrap::where('slug', "{$baseSlug}-1")->exists())->toBeTrue();
    } finally {
        unlink($file);
    }
});

test('fails when plan slug does not exist', function () {
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', ['--plan' => 'nonexistent', '--file' => $file])
            ->expectsOutputToContain(__('Plan not found with slug: :slug', ['slug' => 'nonexistent']))
            ->assertFailed();
    } finally {
        unlink($file);
    }
});

test('fails when plan option is missing', function () {
    $file = tempnam(sys_get_temp_dir(), 'result-');
    file_put_contents($file, '# Result');

    try {
        $this->artisan('plans:result', ['--file' => $file])
            ->expectsOutputToContain(__('Both --plan and --file are required.'))
            ->assertFailed();
    } finally {
        unlink($file);
    }
});

test('fails when file does not exist', function () {
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'my-plan', 'source_type' => 'plan']);

    $this->artisan('plans:result', ['--plan' => 'my-plan', '--file' => '/nonexistent/result.md'])
        ->expectsOutputToContain(__('File not found: :file', ['file' => '/nonexistent/result.md']))
        ->assertFailed();
});
