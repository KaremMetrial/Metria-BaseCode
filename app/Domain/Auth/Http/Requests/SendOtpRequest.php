<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'action' => ['required', 'string', 'in:login,register'],
            'guard' => ['nullable', 'string', 'max:50'],
        ];
    }
}
