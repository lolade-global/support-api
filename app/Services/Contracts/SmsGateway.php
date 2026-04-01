<?php

namespace App\Services\Contracts;

/**
 * Abstraction over the outbound messaging provider. Controllers and jobs
 * depend on this interface, not on Twilio directly, so the provider can
 * be swapped or faked in tests without touching business logic.
 */
interface SmsGateway
{
    /**
     * Send a message and return the provider's message id.
     */
    public function send(string $to, string $body, string $channel): string;
}
