<?php

declare(strict_types=1);

namespace Modules\Auth\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
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
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'action' => ['required', 'string', 'in:login,register'],
            'guard' => ['nullable', 'string', 'max:50'],
        ];
    }
}
