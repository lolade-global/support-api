<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'author_type' => $this->author_type,
            'body' => $this->body,
            'delivery_status' => $this->delivery_status,
            'attachments' => $this->attachments ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
