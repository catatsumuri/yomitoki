<?php

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can store scraps', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.store'), [
            'title' => 'Rough dashboard thought',
            'slug' => 'rough-dashboard-thought',
            'content' => 'Put the writing box first and move review to the side.',
            'organize' => true,
        ]);

    $scrap = Scrap::query()->first();

    expect($scrap)->not->toBeNull();
    $this->assertModelExists($scrap);

    expect($scrap->user_id)->toBe($user->id);
    expect($scrap->source_type)->toBe('note');
    expect($scrap->title)->toBe('Rough dashboard thought');
    expect($scrap->slug)->toBe('rough-dashboard-thought');
    expect($scrap->content)->toContain('writing box first');
    expect($scrap->meta)->toBe(['organize_requested' => true]);

    $response->assertRedirect(route('dashboard.show', ['slug' => 'rough-dashboard-thought']));
    $response->assertInertiaFlash('toast.message', 'Scrap saved. AI organization can be applied next.');
});

test('title is optional when storing scraps', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scraps.store'), [
        'title' => '',
        'slug' => '',
        'content' => 'A title can be inferred later.',
        'organize' => false,
    ]);

    expect(Scrap::query()->first()?->title)->toBe('A title can be inferred later.');
    expect(Scrap::query()->first()?->slug)->toBe('a-title-can-be-inferred-later');
});

test('authenticated users can store child scraps under their own scraps', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'slug' => 'parent-scrap',
    ]);

    $response = $this->actingAs($user)->post(route('scraps.store'), [
        'parent_id' => $parentScrap->id,
        'title' => 'Nested note',
        'slug' => 'nested-note',
        'content' => 'This belongs under the selected scrap.',
        'organize' => false,
    ]);

    $childScrap = Scrap::query()
        ->where('title', 'Nested note')
        ->first();

    expect($childScrap)->not->toBeNull();
    expect($childScrap?->parent_id)->toBe($parentScrap->id);
    expect($childScrap?->slug)->toBeNull();
    $response->assertRedirect(route('dashboard.show', ['slug' => 'parent-scrap']));
});

test('content is required when storing scraps', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('scraps.store'), [
            'title' => 'Incomplete scrap',
            'content' => '',
            'organize' => false,
        ]);

    $response->assertSessionHasErrors('content')
        ->assertRedirect(route('dashboard'));
});

test('users cannot store child scraps under scraps they do not own', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('scraps.store'), [
            'parent_id' => $parentScrap->id,
            'title' => 'Blocked child',
            'content' => 'Blocked content',
            'organize' => false,
        ]);

    $response->assertSessionHasErrors('parent_id')
        ->assertRedirect(route('dashboard'));
});

test('authenticated users can update their scraps', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'title' => 'Old title',
        'content' => 'Old content',
        'content_markdown' => 'Old content',
        'meta' => ['organize_requested' => false],
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('scraps.update', $scrap), [
            'title' => 'Updated title',
            'slug' => 'updated-title',
            'content' => '# Updated content',
            'organize' => true,
        ]);

    $scrap->refresh();

    expect($scrap->title)->toBe('Updated title');
    expect($scrap->slug)->toBe('updated-title');
    expect($scrap->content)->toBe('# Updated content');
    expect($scrap->content_markdown)->toBe('# Updated content');
    expect($scrap->meta)->toBe(['organize_requested' => true]);

    $response->assertRedirect(route('dashboard.show', ['slug' => 'updated-title']));
    $response->assertInertiaFlash('toast.message', 'Scrap updated. AI organization can be applied next.');
});

test('duplicate top level slugs are uniquified on store', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'slug' => 'github-pull-request-draft-pr',
    ]);

    $response = $this->actingAs($user)->post(route('scraps.store'), [
        'title' => 'GitHub pull request draft PR',
        'slug' => 'github-pull-request-draft-pr',
        'content' => 'A second scrap with the same suggested slug.',
        'organize' => false,
    ]);

    $scrap = Scrap::query()
        ->latest('id')
        ->firstOrFail();

    expect($scrap->slug)->toBe('github-pull-request-draft-pr-2');
    $response->assertInertiaFlash(
        'toast.message',
        'Scrap saved. Slug adjusted to github-pull-request-draft-pr-2.',
    );
});

test('duplicate top level slugs are uniquified on update', function () {
    $user = User::factory()->create();

    Scrap::factory()->for($user)->create([
        'slug' => 'github-pull-request-draft-pr',
    ]);

    $scrap = Scrap::factory()->for($user)->create([
        'slug' => 'another-slug',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('scraps.update', $scrap), [
            'title' => 'GitHub pull request draft PR',
            'slug' => 'github-pull-request-draft-pr',
            'content' => 'Updated content',
            'organize' => false,
        ]);

    $scrap->refresh();

    expect($scrap->slug)->toBe('github-pull-request-draft-pr-2');
    $response->assertInertiaFlash(
        'toast.message',
        'Scrap updated. Slug adjusted to github-pull-request-draft-pr-2.',
    );
});

test('top level scraps get fallback title and slug from content when saved without metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scraps.store'), [
        'title' => '',
        'slug' => '',
        'content' => 'Dashboard should always start from capture.',
        'organize' => false,
    ]);

    $scrap = Scrap::query()->firstOrFail();

    expect($scrap->title)->toBe('Dashboard should always start from capture.');
    expect($scrap->slug)->toBe('dashboard-should-always-start-from-capture');
});

test('standalone uploaded asset markdown gets a compact fallback title and slug', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scraps.store'), [
        'title' => '',
        'slug' => '',
        'content' => '![ee49d48159a5-20260511.webp](/images/scraps/1/ZLcnrjCtmBXo3OvdcobhSQjq5)',
        'organize' => false,
    ]);

    $scrap = Scrap::query()->firstOrFail();

    expect($scrap->title)->toBe('ee49d48159a5 20260511');
    expect($scrap->slug)->toBe('ee49d48159a5-20260511');
});

test('fallback titles are shortened to a compact length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('scraps.store'), [
        'title' => '',
        'slug' => '',
        'content' => 'This title should be compact even when the captured text keeps going with extra detail that does not belong in a one line heading.',
        'organize' => false,
    ]);

    $scrap = Scrap::query()->firstOrFail();

    expect(mb_strlen($scrap->title))->toBeLessThanOrEqual(72);
    expect($scrap->title)->toBe('This title should be compact even when the captured text keeps going wit');
});

test('users cannot update scraps they do not own', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->create();

    $this
        ->actingAs($user)
        ->patch(route('scraps.update', $scrap), [
            'title' => 'Blocked',
            'content' => 'Blocked content',
            'organize' => false,
        ])
        ->assertForbidden();
});

test('authenticated users can archive scraps', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'status' => 'raw',
        'slug' => 'archive-me',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.archive', $scrap));

    $scrap->refresh();

    expect($scrap->status)->toBe('archived');

    $response->assertRedirect(route('dashboard'));
    $response->assertInertiaFlash('toast.message', 'Scrap was sent to archive.');
});

test('archiving a parent scrap archives its child scraps', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'status' => 'raw',
        'slug' => 'archive-parent',
    ]);
    $childScrap = Scrap::factory()->for($user)->create([
        'parent_id' => $parentScrap->id,
        'status' => 'processed',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.archive', $parentScrap));

    $parentScrap->refresh();
    $childScrap->refresh();

    expect($parentScrap->status)->toBe('archived');
    expect($childScrap->status)->toBe('archived');

    $response->assertRedirect(route('dashboard'));
    $response->assertInertiaFlash('toast.message', 'Scrap and its child scraps were sent to archive.');
});

test('archiving a child scrap redirects back to the parent scrap detail page', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'slug' => 'archive-parent-detail',
    ]);
    $childScrap = Scrap::factory()->for($user)->create([
        'parent_id' => $parentScrap->id,
        'slug' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.archive', $childScrap));

    $childScrap->refresh();

    expect($childScrap->status)->toBe('archived');
    $response->assertRedirect(route('dashboard.show', ['slug' => 'archive-parent-detail']));
});

test('updating a child scrap redirects back to the parent scrap detail page', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'slug' => 'parent-detail',
    ]);
    $childScrap = Scrap::factory()->for($user)->create([
        'parent_id' => $parentScrap->id,
        'slug' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('scraps.update', $childScrap), [
            'title' => 'Updated child note',
            'content' => 'Updated child content',
            'organize' => false,
        ]);

    $response->assertRedirect(route('dashboard.show', ['slug' => 'parent-detail']));
});

test('users cannot archive scraps they do not own', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('scraps.archive', $scrap))
        ->assertForbidden();
});

test('authenticated users can restore archived top level scraps', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'restore-me',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.restore', $scrap));

    $scrap->refresh();

    expect($scrap->status)->toBe('raw');
    $response->assertRedirect(route('dashboard.show', ['slug' => 'restore-me']));
    $response->assertInertiaFlash('toast.message', 'Scrap was restored.');
});

test('restoring a child scrap also restores archived ancestors', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'restore-parent',
    ]);
    $childScrap = Scrap::factory()->for($user)->create([
        'parent_id' => $parentScrap->id,
        'status' => 'archived',
        'slug' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.restore', $childScrap));

    $parentScrap->refresh();
    $childScrap->refresh();

    expect($parentScrap->status)->toBe('raw');
    expect($childScrap->status)->toBe('raw');
    $response->assertRedirect(route('dashboard.show', ['slug' => 'restore-parent']));
    $response->assertInertiaFlash('toast.message', 'Scrap and related scraps were restored.');
});

test('authenticated users can permanently delete archived scraps', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'delete-me',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('scraps.destroy', $scrap));

    $this->assertModelMissing($scrap);
    $response->assertRedirect(route('archives'));
    $response->assertInertiaFlash('toast.message', 'Scrap was permanently deleted.');
});

test('permanently deleting an archived parent removes its child scraps', function () {
    $user = User::factory()->create();
    $parentScrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'delete-parent',
    ]);
    $childScrap = Scrap::factory()->for($user)->create([
        'parent_id' => $parentScrap->id,
        'status' => 'archived',
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('scraps.destroy', $parentScrap));

    $this->assertModelMissing($parentScrap);
    $this->assertModelMissing($childScrap);
    $response->assertRedirect(route('archives'));
    $response->assertInertiaFlash('toast.message', 'Scrap and its child scraps were permanently deleted.');
});
