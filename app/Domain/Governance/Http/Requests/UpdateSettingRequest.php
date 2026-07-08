<?php

declare(strict_types=1);

namespace App\Domain\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (
            $this->user()->can('governance.settings.manage') || $this->user()->can('admin.super')
        );
    }

    public function rules(): array
    {
        return [
            'value' => ['required'], // any JSON value
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
