<?php

declare(strict_types=1);

namespace Modules\Payment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (
            $this->user()->can('payments.refund') ||
            $this->user()->can('admin.super')
        );
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0.01'], // decimal; omit for full refund
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
