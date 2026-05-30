<?php

use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('deletes scrap with force flag', function () {
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:delete --slug=my-scrap --force')->assertSuccessful();

    expect(Scrap::count())->toBe(0);
});

test('deletes child scraps recursively', function () {
    $user = User::factory()->create();
    $parent = Scrap::factory()->create(['slug' => 'parent', 'user_id' => $user->id]);
    $child = Scrap::factory()->create(['parent_id' => $parent->id, 'user_id' => $user->id]);
    $grandchild = Scrap::factory()->create(['parent_id' => $child->id, 'user_id' => $user->id]);

    $this->artisan('scraps:delete --slug=parent --force')->assertSuccessful();

    expect(Scrap::count())->toBe(0);
});

test('warns about child scraps before deletion', function () {
    $user = User::factory()->create();
    $parent = Scrap::factory()->create(['slug' => 'parent', 'user_id' => $user->id]);
    Scrap::factory()->count(2)->create(['parent_id' => $parent->id, 'user_id' => $user->id]);

    $this->artisan('scraps:delete --slug=parent --force')
        ->expectsOutputToContain('2 child scrap(s)')
        ->assertSuccessful();
});

test('asks for confirmation without force flag', function () {
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:delete --slug=my-scrap')
        ->expectsConfirmation('Delete scrap "my-scrap"? This cannot be undone.', 'yes')
        ->assertSuccessful();

    expect(Scrap::count())->toBe(0);
});

test('does not delete when confirmation denied', function () {
    Scrap::factory()->create(['slug' => 'my-scrap']);

    $this->artisan('scraps:delete --slug=my-scrap')
        ->expectsConfirmation('Delete scrap "my-scrap"? This cannot be undone.', 'no')
        ->assertSuccessful();

    expect(Scrap::count())->toBe(1);
});

test('fails when slug missing', function () {
    $this->artisan('scraps:delete --force')->assertFailed();
});

test('fails when scrap not found', function () {
    $this->artisan('scraps:delete --slug=nonexistent --force')->assertFailed();
});
