<?php

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests cannot download backup', function () {
    $this->get(route('backup.download'))
        ->assertRedirect(route('login'));
});

test('authenticated user can download all scraps as zip', function () {
    $user = User::factory()->create();

    Scrap::factory()->create([
        'user_id' => $user->id,
        'slug' => 'my-scrap',
        'content_markdown' => '# Hello',
        'status' => 'raw',
    ]);

    $response = $this->actingAs($user)->get(route('backup.download'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/zip');
});

test('guests cannot backup a scrap', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->create(['user_id' => $user->id]);

    $this->post(route('scraps.backup', $scrap))
        ->assertRedirect(route('login'));
});

test('authenticated user can backup a scrap to storage', function () {
    Storage::fake();

    $user = User::factory()->create();
    $scrap = Scrap::factory()->create([
        'user_id' => $user->id,
        'slug' => 'my-scrap',
        'content_markdown' => '# Hello',
    ]);

    $this->actingAs($user)
        ->post(route('scraps.backup', $scrap))
        ->assertRedirect();

    Storage::assertExists(
        collect(Storage::allFiles("backups/{$user->id}"))
            ->first(fn ($f) => str_starts_with(basename($f), 'my-scrap-')),
    );
});

test('authenticated user backup includes markdown images for the scrap and its children', function () {
    Storage::fake();

    $user = User::factory()->create();
    $publicImageRoot = storage_path("app/public/scraps/{$user->id}");
    $zipPath = null;

    File::ensureDirectoryExists("{$publicImageRoot}/parent");
    File::ensureDirectoryExists("{$publicImageRoot}/child");
    file_put_contents("{$publicImageRoot}/parent/one.png", 'parent-image');
    file_put_contents("{$publicImageRoot}/child/two.png", 'child-image');

    try {
        $scrap = Scrap::factory()->create([
            'user_id' => $user->id,
            'slug' => 'my-scrap',
            'content_markdown' => '![parent](/images/scraps/'.$user->id.'/parent/one.png)',
        ]);

        Scrap::factory()->create([
            'user_id' => $user->id,
            'parent_id' => $scrap->id,
            'content_markdown' => '![child](/images/scraps/'.$user->id.'/child/two.png)',
        ]);

        $this->actingAs($user)
            ->post(route('scraps.backup', $scrap))
            ->assertRedirect();

        $backupPath = collect(Storage::allFiles("backups/{$user->id}"))
            ->first(fn (string $path) => str_ends_with($path, '.zip'));

        expect($backupPath)->not->toBeNull();

        $zipPath = tempnam(sys_get_temp_dir(), 'backup-test-');
        file_put_contents($zipPath, Storage::get($backupPath));

        $zip = new ZipArchive;

        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->getFromName('images/parent/one.png'))->toBe('parent-image');
        expect($zip->getFromName('images/child/two.png'))->toBe('child-image');

        $zip->close();
    } finally {
        File::deleteDirectory($publicImageRoot);

        if ($zipPath && file_exists($zipPath)) {
            unlink($zipPath);
        }
    }
});

test('authenticated user download preserves image subdirectories in the zip', function () {
    $user = User::factory()->create();
    $publicImageRoot = storage_path("app/public/scraps/{$user->id}");

    File::ensureDirectoryExists("{$publicImageRoot}/alpha");
    File::ensureDirectoryExists("{$publicImageRoot}/beta");
    file_put_contents("{$publicImageRoot}/alpha/shared.png", 'alpha-image');
    file_put_contents("{$publicImageRoot}/beta/shared.png", 'beta-image');

    try {
        Scrap::factory()->create([
            'user_id' => $user->id,
            'slug' => 'my-scrap',
        ]);

        $response = $this->actingAs($user)->get(route('backup.download'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        $zip = new ZipArchive;
        $downloadedZip = $response->baseResponse->getFile()->getPathname();

        expect($zip->open($downloadedZip))->toBeTrue();
        expect($zip->getFromName('images/alpha/shared.png'))->toBe('alpha-image');
        expect($zip->getFromName('images/beta/shared.png'))->toBe('beta-image');

        $zip->close();
    } finally {
        File::deleteDirectory($publicImageRoot);
    }
});

test('user cannot backup another users scrap', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $scrap = Scrap::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)
        ->post(route('scraps.backup', $scrap))
        ->assertForbidden();
});

test('guests cannot access the bulk backup stream', function () {
    $this->get(route('backup.bulk-stream'))
        ->assertRedirect(route('login'));
});

test('bulk backup stream emits SSE progress and complete events', function () {
    Storage::fake();

    $user = User::factory()->create();

    $scrapA = Scrap::factory()->for($user)->create([
        'slug' => 'scrap-a',
        'title' => 'Scrap A',
        'content_markdown' => '# A',
    ]);

    $scrapB = Scrap::factory()->for($user)->create([
        'slug' => 'scrap-b',
        'title' => 'Scrap B',
        'content_markdown' => '# B',
    ]);

    $response = $this->actingAs($user)->get(route('backup.bulk-stream', [
        'ids' => [$scrapA->id, $scrapB->id],
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $body = $response->streamedContent();

    expect($body)
        ->toContain('event: progress')
        ->toContain('"status":"done"')
        ->toContain('event: complete')
        ->toContain('"succeeded":2');

    Storage::assertExists(
        collect(Storage::allFiles("backups/{$user->id}"))
            ->first(fn (string $f) => str_starts_with(basename($f), 'scrap-a-')),
    );

    Storage::assertExists(
        collect(Storage::allFiles("backups/{$user->id}"))
            ->first(fn (string $f) => str_starts_with(basename($f), 'scrap-b-')),
    );
});

test('bulk backup stream silently skips scraps belonging to another user', function () {
    Storage::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownScrap = Scrap::factory()->for($user)->create(['slug' => 'mine', 'content_markdown' => '# Mine']);
    $otherScrap = Scrap::factory()->for($other)->create(['slug' => 'theirs', 'content_markdown' => '# Theirs']);

    $response = $this->actingAs($user)->get(route('backup.bulk-stream', [
        'ids' => [$ownScrap->id, $otherScrap->id],
    ]));

    $response->assertOk();

    $body = $response->streamedContent();

    // Only 1 succeeded — the other user's scrap was silently ignored
    expect($body)->toContain('"succeeded":1');
    Storage::assertMissing("backups/{$user->id}/theirs.zip");
});
