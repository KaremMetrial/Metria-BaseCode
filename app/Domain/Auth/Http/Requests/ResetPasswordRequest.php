<?php

declare(strict_types=1);

namespace App\Domain\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Public authentication flow; rate limiting and credential verification handle authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $passwordRule = Password::min(8);
        if (! app()->environment('testing')) {
            $passwordRule->uncompromised();
        }

        return [
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', $passwordRule, 'confirmed'],
        ];
    }
}
