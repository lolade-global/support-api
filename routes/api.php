<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| API routes. Authenticated endpoints use Sanctum tokens. The inbound
| webhook is unauthenticated but protected by Twilio signature middleware.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);

    // Agent replies are rate-limited per user: 60/min is generous for a
    // human but stops a runaway script or compromised token from flooding
    // the provider (and our Twilio bill).
    Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply'])
        ->middleware('throttle:60,1');
});

// Provider webhook. Signature-verified, not token-authenticated.
Route::post('webhooks/twilio/{workspace}', [WebhookController::class, 'twilioInbound'])
    ->middleware('twilio.signature')
    ->name('webhooks.twilio');
