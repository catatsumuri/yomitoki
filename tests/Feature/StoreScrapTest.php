<?php

use App\Jobs\GenerateScrapEmbeddingJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

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
    Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn (GenerateScrapEmbeddingJob $job) => $job->scrapId === $scrap->id);

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
    Queue::assertPushed(GenerateScrapEmbeddingJob::class, fn (GenerateScrapEmbeddingJob $job) => $job->scrapId === $scrap->id);

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

test('restoring an archived scrap from the articles workspace redirects back to articles', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'restore-from-articles',
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeader('referer', route('articles.show', [
            'slug' => 'restore-from-articles',
            'status' => 'archived',
        ]))
        ->post(route('scraps.restore', $scrap));

    $response->assertRedirect(route('articles.show', ['slug' => 'restore-from-articles']));
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
    $response->assertRedirect(route('dashboard', ['status' => 'archived']));
    $response->assertInertiaFlash('toast.message', 'Scrap was permanently deleted.');
});

test('permanently deleting an archived scrap from the articles workspace redirects back to articles', function () {
    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'status' => 'archived',
        'slug' => 'delete-from-articles',
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeader('referer', route('articles.show', [
            'slug' => 'delete-from-articles',
            'status' => 'archived',
        ]))
        ->delete(route('scraps.destroy', $scrap));

    $this->assertModelMissing($scrap);
    $response->assertRedirect(route('articles', ['status' => 'archived']));
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
    $response->assertRedirect(route('dashboard', ['status' => 'archived']));
    $response->assertInertiaFlash('toast.message', 'Scrap and its child scraps were permanently deleted.');
});

test('authenticated users can bulk archive multiple scraps', function () {
    $user = User::factory()->create();

    $scrapA = Scrap::factory()->for($user)->create(['status' => 'raw', 'slug' => 'bulk-a']);
    $scrapB = Scrap::factory()->for($user)->create(['status' => 'processed', 'slug' => 'bulk-b']);
    $child = Scrap::factory()->for($user)->create([
        'parent_id' => $scrapA->id,
        'status' => 'raw',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('scraps.bulk-archive'), ['ids' => [$scrapA->id, $scrapB->id]]);

    expect($scrapA->fresh()->status)->toBe('archived');
    expect($scrapB->fresh()->status)->toBe('archived');
    expect($child->fresh()->status)->toBe('archived');

    $response->assertRedirect(route('articles'));
    $response->assertInertiaFlash('toast.message', '2 件をアーカイブしました。');
});

test('bulk archive silently ignores scraps belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $ownScrap = Scrap::factory()->for($user)->create(['status' => 'raw']);
    $otherScrap = Scrap::factory()->for($other)->create(['status' => 'raw']);

    $this->actingAs($user)->post(route('scraps.bulk-archive'), [
        'ids' => [$ownScrap->id, $otherScrap->id],
    ]);

    expect($ownScrap->fresh()->status)->toBe('archived');
    expect($otherScrap->fresh()->status)->toBe('raw');
});

test('guests cannot bulk archive scraps', function () {
    $this->post(route('scraps.bulk-archive'), ['ids' => [1]])
        ->assertRedirect(route('login'));
});
