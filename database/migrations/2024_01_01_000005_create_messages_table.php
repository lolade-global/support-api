<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('author_type')->default('contact'); // contact, agent, system
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('direction'); // inbound, outbound
            $table->text('body');
            $table->string('delivery_status')->default('queued');
            // Provider message id (e.g. Twilio SID). Unique so a webhook
            // replay can't insert the same message twice — idempotency.
            $table->string('external_id')->nullable()->unique();
            $table->json('attachments')->nullable();
            $table->timestamps();

            // Loading a conversation thread reads messages by conversation
            // ordered by time — this index serves that directly.
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
