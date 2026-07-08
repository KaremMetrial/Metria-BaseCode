<?php

declare(strict_types=1);

namespace App\Domain\Payment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (
            $this->user()->can('payments.create') ||
            $this->user()->can('admin.super')
        );
    }

    public function rules(): array
    {
        $gateways = array_keys(config('payments.gateways', []));

        return [
            'amount' => ['required', 'numeric', 'min:0.01'], // decimal, e.g. 150.50
            'currency' => ['nullable', 'string', 'size:3'],
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $gateways)],
            'description' => ['nullable', 'string', 'max:500'],
            'return_url' => ['nullable', 'url'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
