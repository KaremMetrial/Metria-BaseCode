<?php

declare(strict_types=1);

namespace Modules\Communication\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Communication\Domain\Enums\ConversationType;

final class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ConversationType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['present', 'array', 'max:100'],
            'participant_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
