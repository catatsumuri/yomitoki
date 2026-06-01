<?php

use App\Ai\Agents\GenerateScrapTagsAgent;
use App\Jobs\GenerateScrapTagsJob;
use App\Models\Scrap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('job stores normalized tags on the scrap meta', function () {
    GenerateScrapTagsAgent::fake([
        ['tags' => '認証, バグ, 認証 , API'],
    ]);

    $user = User::factory()->create();
    $scrap = Scrap::factory()->for($user)->create([
        'content' => 'ログインに関するメモ',
        'meta' => ['seeded' => true],
    ]);

    GenerateScrapTagsJob::dispatchSync($scrap->id);

    $scrap->refresh();

    expect($scrap->meta['tags'])->toBe(['認証', 'バグ', 'API']);
});
