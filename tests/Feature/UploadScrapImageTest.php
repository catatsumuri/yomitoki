<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;

uses(RefreshDatabase::class);

test('authenticated users can upload inline scrap images', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('scraps.upload-image'), [
            'image' => UploadedFile::fake()->image('capture.png', 1200, 900),
            'upload_key' => 'scrap-image-1',
        ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertInertiaFlash('scrapImageUpload.key', 'scrap-image-1');
    $response->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => isset($flash['scrapImageUpload']['url'])
        && is_string($flash['scrapImageUpload']['url'])
        && str_starts_with($flash['scrapImageUpload']['url'], '/images/scraps/'.$user->id.'/')
    );
    expect(Storage::disk('public')->files('scraps/'.$user->id))
        ->toHaveCount(1);
});

test('authenticated users can upload inline scrap documents', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('scraps.upload-image'), [
            'image' => UploadedFile::fake()->create(
                'requirements.docx',
                100,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'upload_key' => 'scrap-file-1',
        ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertInertiaFlash('scrapImageUpload.key', 'scrap-file-1');
    $response->assertSessionHas(SessionKey::FLASH_DATA, fn (array $flash) => isset($flash['scrapImageUpload']['url'])
        && is_string($flash['scrapImageUpload']['url'])
        && str_starts_with($flash['scrapImageUpload']['url'], '/images/scraps/'.$user->id.'/')
    );
    expect(Storage::disk('public')->files('scraps/'.$user->id))
        ->toHaveCount(1);
});

test('inline scrap file upload rejects unsupported files', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('scraps.upload-image'), [
            'image' => UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream'),
        ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHasErrors('image');
});
