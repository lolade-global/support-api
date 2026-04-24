<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InboundWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Signature verification happens in dedicated middleware before we
        // get here, so by this point the request is trusted.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string'],
            'body' => ['required', 'string'],
            'message_sid' => ['required', 'string'],
            'channel' => ['sometimes', 'string', 'in:sms,whatsapp'],
        ];
    }
}
