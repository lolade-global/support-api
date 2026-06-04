<?php

use App\Jobs\DeliverOutboundMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('lets an agent reply and queues delivery', function () {
    Queue::fake();

    $agent = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'workspace_id' => $agent->workspace_id,
    ]);

    $response = $this->actingAs($agent)
        ->postJson("/api/conversations/{$conversation->id}/reply", [
            'body' => 'Thanks for reaching out — happy to help!',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.direction', 'outbound')
        ->assertJsonPath('data.delivery_status', 'queued');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'direction' => 'outbound',
    ]);

    // The controller should hand delivery to the queue, not send inline.
    Queue::assertPushed(DeliverOutboundMessage::class);
});

it('forbids replying to a conversation in another workspace', function () {
    $agent = User::factory()->create();
    $otherConversation = Conversation::factory()->create(); // different workspace

    $this->actingAs($agent)
        ->postJson("/api/conversations/{$otherConversation->id}/reply", [
            'body' => 'Should not be allowed',
        ])
        ->assertForbidden();
});

it('validates that a reply body is required', function () {
    $agent = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'workspace_id' => $agent->workspace_id,
    ]);

    $this->actingAs($agent)
        ->postJson("/api/conversations/{$conversation->id}/reply", ['body' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('body');
});
