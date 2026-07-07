<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OtpRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
            'guard' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'locale' => ['nullable', 'string', 'in:'.implode(',', config('localization.supported', ['en', 'ar']))],
        ];
    }
}
