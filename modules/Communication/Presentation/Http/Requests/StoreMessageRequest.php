<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_message_id' => ['nullable', 'uuid'],
            'kind' => ['required', 'string', 'max:48'],
            'content' => ['required', 'array', 'max:20'],
        ];
    }
}
