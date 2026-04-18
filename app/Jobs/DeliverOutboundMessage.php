<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Contracts\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers an outbound message to the customer via the channel provider
 * (e.g. Twilio for SMS/WhatsApp). Runs on the Redis "outbound" queue,
 * which Horizon supervises in production.
 */
class DeliverOutboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry a few times — provider blips shouldn't drop a customer reply. */
    public int $tries = 5;

    /** Exponential-ish backoff between retries, in seconds. */
    public array $backoff = [10, 30, 60, 120];

    /** Give a single attempt a hard ceiling so a hung HTTP call can't wedge a worker. */
    public int $timeout = 30;

    public function __construct(public int $messageId)
    {
    }

    /**
     * A unique lock so the same message is never delivered twice even if
     * the job is dispatched more than once. Ties into ShouldBeUnique-style
     * safety at the provider boundary.
     */
    public function uniqueId(): string
    {
        return 'deliver-message-' . $this->messageId;
    }

    public function handle(SmsGateway $gateway): void
    {
        $message = Message::find($this->messageId);

        // Message may have been deleted, or already delivered by an
        // earlier attempt — nothing to do.
        if ($message === null || $message->delivery_status === Message::DELIVERY_DELIVERED) {
            return;
        }

        $conversation = $message->conversation;
        $to = $conversation->contact->phone;

        $providerId = $gateway->send(
            to: $to,
            body: $message->body,
            channel: $conversation->channel,
        );

        $message->forceFill([
            'delivery_status' => Message::DELIVERY_SENT,
            'external_id' => $providerId,
        ])->save();
    }

    /**
     * Called after the final retry fails. Mark the row so the UI can show
     * a "failed to send — retry" affordance instead of silently losing it.
     */
    public function failed(Throwable $e): void
    {
        Message::where('id', $this->messageId)
            ->update(['delivery_status' => Message::DELIVERY_FAILED]);
    }
}
