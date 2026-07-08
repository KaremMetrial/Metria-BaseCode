<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (
            $this->user()->can('webhooks.manage') ||
            $this->user()->can('admin.super')
        );
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
