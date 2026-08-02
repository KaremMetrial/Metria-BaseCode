<?php

declare(strict_types=1);

namespace App\Domain\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOAuthProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.oauth.manage') || $this->user()?->can('admin.super') ?: false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string'],
            'redirect_url' => ['required', 'url', 'max:500'],
            'scopes' => ['nullable', 'array'],
            'is_enabled' => ['boolean'],
        ];
    }
}
