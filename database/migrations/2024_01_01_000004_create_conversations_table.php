<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('channel')->default('web'); // web, whatsapp, sms, email
            $table->string('subject')->nullable();
            $table->string('status')->default('open'); // open, pending, closed
            $table->unsignedTinyInteger('priority')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // The agent inbox is filtered by workspace + status and sorted
            // by recency. This composite index backs that exact access
            // pattern so the list view never does a filesort on a big table.
            $table->index(['workspace_id', 'status', 'last_message_at']);

            // Backs the "my assigned conversations" view.
            $table->index(['assigned_agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
