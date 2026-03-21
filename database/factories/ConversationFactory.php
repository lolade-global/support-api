<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'contact_id' => Contact::factory(),
            'assigned_agent_id' => null,
            'channel' => fake()->randomElement(['web', 'sms', 'whatsapp']),
            'subject' => fake()->sentence(4),
            'status' => Conversation::STATUS_OPEN,
            'priority' => 0,
            'last_message_at' => now(),
            'metadata' => [],
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => Conversation::STATUS_CLOSED]);
    }
}
