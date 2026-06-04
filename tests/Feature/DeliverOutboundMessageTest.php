<?php

use App\Jobs\DeliverOutboundMessage;
use App\Models\Message;
use App\Services\Contracts\SmsGateway;
use App\Services\FakeSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends via the gateway and marks the message sent', function () {
    // Swap the real gateway for the fake in the container. This is the
    // "mocking services" pattern — the job under test has no idea it isn't
    // talking to Twilio.
    $fake = new FakeSmsGateway();
    $this->app->instance(SmsGateway::class, $fake);

    $message = Message::factory()->create([
        'direction' => Message::DIRECTION_OUTBOUND,
        'delivery_status' => Message::DELIVERY_QUEUED,
        'body' => 'Your order shipped!',
    ]);

    // Run the job synchronously.
    (new DeliverOutboundMessage($message->id))->handle($fake);

    expect($fake->sent)->toHaveCount(1)
        ->and($fake->sent[0]['body'])->toBe('Your order shipped!');

    $message->refresh();
    expect($message->delivery_status)->toBe(Message::DELIVERY_SENT)
        ->and($message->external_id)->toStartWith('FAKE_');
});

it('does nothing when the message is already delivered', function () {
    $fake = new FakeSmsGateway();

    $message = Message::factory()->create([
        'delivery_status' => Message::DELIVERY_DELIVERED,
    ]);

    (new DeliverOutboundMessage($message->id))->handle($fake);

    expect($fake->sent)->toBeEmpty();
});
