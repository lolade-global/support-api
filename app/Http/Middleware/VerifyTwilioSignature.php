<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the X-Twilio-Signature header so we only accept inbound webhooks
 * that genuinely came from Twilio. Without this, anyone who knows the URL
 * could inject fake customer messages. The signature is an HMAC-SHA1 of the
 * full URL + sorted POST params, keyed with the account auth token.
 */
class VerifyTwilioSignature
{
    public function __construct(private string $authToken)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Twilio-Signature', '');
        $expected = $this->computeSignature(
            $request->fullUrl(),
            $request->post()
        );

        // hash_equals is constant-time to avoid timing attacks.
        if (! hash_equals($expected, $signature)) {
            abort(403, 'Invalid webhook signature.');
        }

        return $next($request);
    }

    private function computeSignature(string $url, array $params): string
    {
        ksort($params);
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        return base64_encode(hash_hmac('sha1', $data, $this->authToken, true));
    }
}
