<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Fine-grained authorization is done via the policy in the
        // controller; here we just require an authenticated user.
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:4000'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*.url' => ['required_with:attachments', 'url'],
            'attachments.*.type' => ['required_with:attachments', 'string', 'in:image,file,audio'],
        ];
    }
}
