<?php

use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes conversations to a workspace', function () {
    $a = Workspace::factory()->create();
    $b = Workspace::factory()->create();

    Conversation::factory()->count(3)->create(['workspace_id' => $a->id]);
    Conversation::factory()->count(2)->create(['workspace_id' => $b->id]);

    expect(Conversation::forWorkspace($a->id)->count())->toBe(3)
        ->and(Conversation::forWorkspace($b->id)->count())->toBe(2);
});

it('filters by a value inside the metadata JSON column', function () {
    $workspace = Workspace::factory()->create();

    Conversation::factory()->create([
        'workspace_id' => $workspace->id,
        'metadata' => ['source' => 'shopify'],
    ]);
    Conversation::factory()->create([
        'workspace_id' => $workspace->id,
        'metadata' => ['source' => 'web'],
    ]);

    expect(Conversation::whereMetadata('source', 'shopify')->count())->toBe(1);
});
