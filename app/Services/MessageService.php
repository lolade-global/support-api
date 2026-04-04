<?php

namespace App\Services;

use App\Events\MessageCreated;
use App\Jobs\DeliverOutboundMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the write-side logic for messages so controllers stay
 * thin and the same rules apply whether a message arrives via the API,
 * a provider webhook, or an internal command.
 */
class MessageService
{
    /**
     * Record an inbound message (customer -> business).
     *
     * $externalId is the provider's message id. We use it to stay
     * idempotent: a webhook that fires twice must not create two rows.
     */
    public function recordInbound(
        Conversation $conversation,
        string $body,
        ?string $externalId = null,
        array $attachments = []
    ): Message {
        // If we've already stored this provider message, return the
        // existing row instead of inserting a duplicate. This is the
        // same idempotency discipline I used for payment webhooks.
        if ($externalId !== null) {
            $existing = Message::where('external_id', $externalId)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($conversation, $body, $externalId, $attachments) {
            $message = $conversation->messages()->create([
                'author_type' => 'contact',
                'author_id' => $conversation->contact_id,
                'direction' => Message::DIRECTION_INBOUND,
                'body' => $body,
                'delivery_status' => Message::DELIVERY_DELIVERED,
                'external_id' => $externalId,
                'attachments' => $attachments,
            ]);

            // Reopen a closed conversation when the customer replies, and
            // bump recency so it climbs the inbox. Done in the same
            // transaction as the insert so the two never diverge.
            $conversation->forceFill([
                'status' => Conversation::STATUS_OPEN,
                'last_message_at' => $message->created_at,
            ])->save();

            // Broadcast after commit so subscribers never see a message
            // that a rolled-back transaction would have erased.
            DB::afterCommit(fn () => broadcast(new MessageCreated($message)));

            return $message;
        });
    }

    /**
     * Queue an outbound message (agent -> customer). The row is created
     * as "queued" synchronously so the agent gets an immediate response;
     * the actual provider send happens on the queue.
     */
    public function sendOutbound(
        Conversation $conversation,
        int $agentId,
        string $body,
        array $attachments = []
    ): Message {
        $message = DB::transaction(function () use ($conversation, $agentId, $body, $attachments) {
            $message = $conversation->messages()->create([
                'author_type' => 'agent',
                'author_id' => $agentId,
                'direction' => Message::DIRECTION_OUTBOUND,
                'body' => $body,
                'delivery_status' => Message::DELIVERY_QUEUED,
                'attachments' => $attachments,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
            ])->save();

            return $message;
        });

        // Hand off delivery to the queue. Dispatched after the row exists
        // so the worker always finds it.
        DeliverOutboundMessage::dispatch($message->id)->onQueue('outbound');

        broadcast(new MessageCreated($message));

        return $message;
    }
}
