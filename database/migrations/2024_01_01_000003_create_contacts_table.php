<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            // A contact is looked up by email or phone within a workspace
            // on every inbound message, so these compound indexes matter.
            $table->index(['workspace_id', 'email']);
            $table->index(['workspace_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
