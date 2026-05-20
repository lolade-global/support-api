<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Broadcast channel authorization. A user may subscribe to a conversation's
| private channel only if it belongs to their workspace.
*/

Broadcast::channel('conversations.{conversationId}', function (User $user, int $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation !== null
        && $conversation->workspace_id === $user->workspace_id;
});
