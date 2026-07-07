<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtpLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'guard' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
