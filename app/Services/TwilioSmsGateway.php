<?php

namespace App\Services;

use App\Services\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Twilio-backed implementation. Kept deliberately thin: it knows how to
 * talk to Twilio and nothing about our domain. Uses Laravel's HTTP client
 * so it's trivially fakeable with Http::fake() in tests.
 */
class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $fromNumber,
    ) {
    }

    public function send(string $to, string $body, string $channel): string
    {
        // WhatsApp numbers are prefixed per Twilio's convention.
        $from = $channel === 'whatsapp' ? "whatsapp:{$this->fromNumber}" : $this->fromNumber;
        $to = $channel === 'whatsapp' ? "whatsapp:{$to}" : $to;

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Twilio send failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        return $response->json('sid');
    }
}
