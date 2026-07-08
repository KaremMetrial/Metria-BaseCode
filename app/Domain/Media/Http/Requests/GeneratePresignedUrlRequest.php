<?php

declare(strict_types=1);

namespace App\Domain\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePresignedUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (
            $this->user()->can('media.upload') ||
            $this->user()->can('media.manage') ||
            $this->user()->can('admin.super')
        );
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'size' => ['required', 'integer', 'min:1'],
            'is_public' => ['sometimes', 'boolean'],
            'purpose' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
