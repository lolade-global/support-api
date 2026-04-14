<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InboundWebhookRequest;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * Handle an inbound Twilio message. Finds or creates the contact and
     * an open conversation, then records the message idempotently using
     * the provider's message SID.
     */
    public function twilioInbound(InboundWebhookRequest $request): JsonResponse
    {
        // In a real multi-tenant setup the workspace is resolved from the
        // receiving number; hard-coding the lookup here for clarity.
        $workspaceId = (int) $request->route('workspace');

        $contact = Contact::firstOrCreate(
            ['workspace_id' => $workspaceId, 'phone' => $request->validated('from')],
            ['name' => $request->validated('from')]
        );

        $conversation = Conversation::firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'status' => Conversation::STATUS_OPEN,
            ],
            [
                'channel' => $request->validated('channel', 'sms'),
                'last_message_at' => now(),
            ]
        );

        $message = $this->messages->recordInbound(
            conversation: $conversation,
            body: $request->validated('body'),
            externalId: $request->validated('message_sid'),
        );

        return response()->json(['id' => $message->id], 202);
    }
}
