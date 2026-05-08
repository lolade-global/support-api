<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Authorization for conversations. The core rule across the whole app:
 * you may only touch conversations inside your own workspace. Admins can
 * reassign; agents can act on their own or unassigned conversations.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->workspace_id === $conversation->workspace_id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        if ($user->workspace_id !== $conversation->workspace_id) {
            return false;
        }

        if ($conversation->isClosed()) {
            return false;
        }

        return $conversation->assigned_agent_id === null
            || $conversation->assigned_agent_id === $user->id
            || $user->isAdmin();
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        return $user->workspace_id === $conversation->workspace_id;
    }
}
