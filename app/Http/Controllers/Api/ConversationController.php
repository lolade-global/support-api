<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * Paginated inbox for the current user's workspace.
     *
     * Eager-loads contact + assigned agent to avoid the classic N+1 where
     * rendering a 50-row inbox fires 100 extra queries. withCount adds the
     * message tally without loading the messages themselves.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaceId = $request->user()->workspace_id;

        $conversations = Conversation::query()
            ->forWorkspace($workspaceId)
            ->with(['contact', 'agent'])
            ->withCount('messages')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('unassigned'), fn ($q) => $q->unassignedQueue())
            ->orderByDesc('last_message_at')
            ->paginate(perPage: min((int) $request->query('per_page', 25), 100));

        return ConversationResource::collection($conversations);
    }

    /**
     * Single conversation with its message thread (paginated).
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);

        $conversation->load(['contact', 'agent']);

        return new ConversationResource($conversation);
    }

    /**
     * Post an agent reply. FormRequest handles validation; the policy
     * handles "may this agent reply here"; the service handles the write
     * plus queueing delivery. The controller just wires them together.
     */
    public function reply(StoreMessageRequest $request, Conversation $conversation): MessageResource
    {
        $this->authorize('reply', $conversation);

        $message = $this->messages->sendOutbound(
            conversation: $conversation,
            agentId: $request->user()->id,
            body: $request->validated('body'),
            attachments: $request->validated('attachments', []),
        );

        return new MessageResource($message);
    }
}
