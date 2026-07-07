<?php

declare(strict_types=1);

namespace App\Domain\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checksum' => ['required', 'string', 'size:64'], // SHA-256 length is 64 characters
        ];
    }
}
