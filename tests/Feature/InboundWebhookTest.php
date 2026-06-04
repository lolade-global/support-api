<?php

use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Bypass signature middleware for these tests; it has its own unit test.
    $this->withoutMiddleware(\App\Http\Middleware\VerifyTwilioSignature::class);
});

it('creates a contact, conversation, and message from an inbound webhook', function () {
    $workspace = Workspace::factory()->create();

    $payload = [
        'from' => '+15551234567',
        'body' => 'Hi, my order has not arrived',
        'message_sid' => 'SM_test_123',
        'channel' => 'sms',
    ];

    $this->postJson("/api/webhooks/twilio/{$workspace->id}", $payload)
        ->assertStatus(202);

    $this->assertDatabaseHas('contacts', [
        'workspace_id' => $workspace->id,
        'phone' => '+15551234567',
    ]);
    $this->assertDatabaseHas('messages', [
        'body' => 'Hi, my order has not arrived',
        'external_id' => 'SM_test_123',
        'direction' => 'inbound',
    ]);
});

it('is idempotent when the same webhook fires twice', function () {
    $workspace = Workspace::factory()->create();

    $payload = [
        'from' => '+15551234567',
        'body' => 'duplicate delivery test',
        'message_sid' => 'SM_dupe_1',
        'channel' => 'sms',
    ];

    // Twilio retries on timeout, so the same SID can arrive more than once.
    $this->postJson("/api/webhooks/twilio/{$workspace->id}", $payload)->assertStatus(202);
    $this->postJson("/api/webhooks/twilio/{$workspace->id}", $payload)->assertStatus(202);

    // Exactly one message row despite two deliveries.
    expect(\App\Models\Message::where('external_id', 'SM_dupe_1')->count())->toBe(1);
});
