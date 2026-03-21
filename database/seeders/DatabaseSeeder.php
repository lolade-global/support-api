<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::factory()->create([
            'name' => 'Acme Support',
            'slug' => 'acme-support',
        ]);

        $admin = User::factory()->admin()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Ada Admin',
            'email' => 'admin@acme.test',
        ]);

        User::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        // A handful of conversations, each with a short message thread.
        Contact::factory()
            ->count(20)
            ->for($workspace)
            ->create()
            ->each(function (Contact $contact) use ($workspace) {
                $conversation = Conversation::factory()->create([
                    'workspace_id' => $workspace->id,
                    'contact_id' => $contact->id,
                ]);

                Message::factory()
                    ->count(rand(1, 6))
                    ->create(['conversation_id' => $conversation->id]);
            });
    }
}
