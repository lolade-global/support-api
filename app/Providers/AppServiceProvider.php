<?php

namespace App\Providers;

use App\Http\Middleware\VerifyTwilioSignature;
use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use App\Services\Contracts\SmsGateway;
use App\Services\FakeSmsGateway;
use App\Services\TwilioSmsGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the SmsGateway interface to a concrete implementation.
        // Local/testing use the fake so no real messages go out and no
        // Twilio creds are needed; production uses Twilio. Everything that
        // depends on SmsGateway is oblivious to which one it gets.
        $this->app->singleton(SmsGateway::class, function ($app) {
            if ($app->environment('production', 'staging')) {
                return new TwilioSmsGateway(
                    accountSid: config('services.twilio.sid'),
                    authToken: config('services.twilio.token'),
                    fromNumber: config('services.twilio.from'),
                );
            }

            return new FakeSmsGateway();
        });

        // Bind the webhook-signature middleware with the account token.
        $this->app->when(VerifyTwilioSignature::class)
            ->needs('$authToken')
            ->give(fn () => config('services.twilio.token', 'test-token'));
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
    }
}
