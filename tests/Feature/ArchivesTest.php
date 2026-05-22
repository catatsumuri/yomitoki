<?php

use App\Models\Scrap;
use App\Models\User;
use Database\Seeders\DashboardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get(route('archives'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view archived scraps', function () {
    $this->seed(DashboardDemoSeeder::class);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $childArchive = Scrap::factory()->for($user)->create([
        'title' => '子記事: 破棄した補足メモ',
        'slug' => null,
        'status' => 'archived',
        'parent_id' => Scrap::query()
            ->where('user_id', $user->id)
            ->where('slug', 'spec-generation-flow')
            ->value('id'),
    ]);

    $response = $this->actingAs($user)->get(route('archives'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', config('app.name'))
            ->where('summary.archivedCount', 2)
            ->where('summary.topLevelCount', 1)
            ->where('summary.nestedCount', 1)
            ->has('archivedItems.data', 2)
            ->where('archivedItems.data', fn (Collection $items): bool => $items->contains(
                fn (array $item): bool => $item['title'] === $childArchive->title
                    && $item['isNested'] === true
                    && data_get($item, 'parent.slug') === 'spec-generation-flow',
            ) && $items->contains(
                fn (array $item): bool => $item['slug'] === 'legacy-dashboard-metrics',
            ))
        );
});
