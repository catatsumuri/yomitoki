<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Jobs\SummarizeScrapJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('saves plan markdown to database as a scrap', function () {
    Queue::fake();
    $user = User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, "# My Plan\n\nSome content here.");

    try {
        $this->artisan('plans:save', ['--title' => 'my-plan', '--file' => $file])
            ->assertSuccessful();

        $scrap = Scrap::first();

        expect($scrap)->not->toBeNull()
            ->and($scrap->title)->toBe('my-plan')
            ->and($scrap->source_type)->toBe('plan')
            ->and($scrap->status)->toBe('processed')
            ->and($scrap->slug)->toBe('my-plan')
            ->and($scrap->user_id)->toBe($user->id)
            ->and($scrap->content_markdown)->toContain('# My Plan');
    } finally {
        unlink($file);
    }
});

test('uses provided slug without forcing title to match it', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', [
            '--title' => 'Codex向けプラン保存フロー移植',
            '--slug' => 'codex-plan-skill-port',
            '--file' => $file,
        ])->assertSuccessful();

        $scrap = Scrap::first();

        expect($scrap->title)->toBe('Codex向けプラン保存フロー移植')
            ->and($scrap->slug)->toBe('codex-plan-skill-port');
    } finally {
        unlink($file);
    }
});

test('dispatches embedding and summary jobs after saving', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan Content');

    try {
        $this->artisan('plans:save', ['--title' => 'my-plan', '--file' => $file])
            ->assertSuccessful();

        Queue::assertPushed(GenerateScrapEmbeddingJob::class);
        Queue::assertPushed(SummarizeScrapJob::class);
    } finally {
        unlink($file);
    }
});

test('stores project metadata in meta field', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', [
            '--title' => 'my-plan',
            '--file' => $file,
            '--project' => 'my-project',
            '--directory' => '/home/user/my-project',
        ])->assertSuccessful();

        $scrap = Scrap::first();

        expect($scrap->meta['project'])->toBe('my-project')
            ->and($scrap->meta['directory'])->toBe('/home/user/my-project')
            ->and($scrap->meta['created_from'])->toBe('plan-to-markdown-skill')
            ->and($scrap->meta['tags'])->toBe(['my-project']);
    } finally {
        unlink($file);
    }
});

test('stores created from metadata when provided', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', [
            '--title' => 'my-plan',
            '--file' => $file,
            '--created-from' => 'codex-plan-to-markdown-skill',
        ])->assertSuccessful();

        expect(Scrap::first()->meta['created_from'])->toBe('codex-plan-to-markdown-skill');
    } finally {
        unlink($file);
    }
});

test('derives project name from directory basename when project not specified', function () {
    Queue::fake();
    User::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', [
            '--title' => 'my-plan',
            '--file' => $file,
            '--directory' => '/home/user/awesome-app',
        ])->assertSuccessful();

        expect(Scrap::first()->meta['project'])->toBe('awesome-app')
            ->and(Scrap::first()->meta['tags'])->toBe(['awesome-app']);
    } finally {
        unlink($file);
    }
});

test('generates unique slug when title already exists', function () {
    Queue::fake();
    $user = User::factory()->create();
    Scrap::factory()->for($user)->create(['slug' => 'my-plan']);

    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# My Plan');

    try {
        $this->artisan('plans:save', ['--title' => 'my-plan', '--file' => $file])
            ->assertSuccessful();

        expect(Scrap::where('slug', 'my-plan-1')->exists())->toBeTrue();
    } finally {
        unlink($file);
    }
});

test('fails when title is missing', function () {
    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', ['--file' => $file])
            ->expectsOutputToContain(__('Both --title and --file are required.'))
            ->assertFailed();
    } finally {
        unlink($file);
    }
});

test('fails when file does not exist', function () {
    User::factory()->create();

    $this->artisan('plans:save', ['--title' => 'test', '--file' => '/nonexistent/path.md'])
        ->expectsOutputToContain(__('File not found: :file', ['file' => '/nonexistent/path.md']))
        ->assertFailed();
});

test('fails when no user exists', function () {
    $file = tempnam(sys_get_temp_dir(), 'plan-');
    file_put_contents($file, '# Plan');

    try {
        $this->artisan('plans:save', ['--title' => 'test', '--file' => $file])
            ->expectsOutputToContain(__('No user found.'))
            ->assertFailed();
    } finally {
        unlink($file);
    }
});
