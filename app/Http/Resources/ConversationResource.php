<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the API response for a conversation. whenLoaded() ensures we only
 * serialize relationships that were actually eager-loaded, so the resource
 * never triggers a lazy query behind our back.
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'messages_count' => $this->whenCounted('messages'),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'metadata' => $this->metadata,
        ];
    }
}
