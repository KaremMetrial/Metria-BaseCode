<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'starts_with:https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
