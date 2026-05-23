<?php

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('backup command stores all-scrap archives with preserved image paths', function () {
    Storage::fake();

    $user = User::factory()->create();
    $publicImageRoot = storage_path("app/public/scraps/{$user->id}");
    $zipPath = null;

    File::ensureDirectoryExists("{$publicImageRoot}/alpha");
    File::ensureDirectoryExists("{$publicImageRoot}/beta");
    file_put_contents("{$publicImageRoot}/alpha/shared.png", 'alpha-image');
    file_put_contents("{$publicImageRoot}/beta/shared.png", 'beta-image');

    try {
        Scrap::factory()->count(2)->for($user)->create();

        $this->artisan('scraps:backup --all')
            ->expectsOutputToContain('Backup saved: storage/app/private/backups/')
            ->assertSuccessful();

        $backupPath = collect(Storage::allFiles('backups'))
            ->first(fn (string $path) => str_ends_with($path, '.zip'));

        expect($backupPath)->not->toBeNull();

        $zipPath = tempnam(sys_get_temp_dir(), 'backup-command-all-');
        file_put_contents($zipPath, Storage::get($backupPath));

        $zip = new ZipArchive;

        expect($zip->open($zipPath))->toBeTrue();
        expect($zip->getFromName('images/alpha/shared.png'))->toBe('alpha-image');
        expect($zip->getFromName('images/beta/shared.png'))->toBe('beta-image');

        $zip->close();
    } finally {
        File::deleteDirectory($publicImageRoot);

        if ($zipPath && file_exists($zipPath)) {
            unlink($zipPath);
        }
    }
});

test('backup command includes markdown images for the scrap and its children', function () {
    $user = User::factory()->create();
    $publicImageRoot = storage_path("app/public/scraps/{$user->id}");
    $outputDir = storage_path('framework/testing/backup-command-output');

    File::ensureDirectoryExists("{$publicImageRoot}/parent");
    File::ensureDirectoryExists("{$publicImageRoot}/child");
    File::ensureDirectoryExists($outputDir);
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

        $this->artisan('scraps:backup my-scrap --output='.$outputDir)
            ->expectsOutputToContain('Backup saved:')
            ->assertSuccessful();

        $createdZip = collect(File::files($outputDir))
            ->first(fn (SplFileInfo $file) => $file->getExtension() === 'zip');

        expect($createdZip)->not->toBeNull();

        $zip = new ZipArchive;

        expect($zip->open($createdZip->getPathname()))->toBeTrue();
        expect($zip->getFromName('images/parent/one.png'))->toBe('parent-image');
        expect($zip->getFromName('images/child/two.png'))->toBe('child-image');

        $zip->close();
    } finally {
        File::deleteDirectory($publicImageRoot);
        File::deleteDirectory($outputDir);
    }
});
