<?php

namespace App\Services;

use App\Services\Contracts\SmsGateway;
use Illuminate\Support\Str;

/**
 * In-memory gateway for local dev and tests. Records what it "sent" so
 * assertions can inspect it, and returns a fake provider id.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var array<int, array{to: string, body: string, channel: string}> */
    public array $sent = [];

    public function send(string $to, string $body, string $channel): string
    {
        $this->sent[] = compact('to', 'body', 'channel');

        return 'FAKE_' . Str::upper(Str::random(20));
    }
}
