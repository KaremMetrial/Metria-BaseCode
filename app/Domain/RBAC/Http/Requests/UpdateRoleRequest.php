<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rbac.roles.manage') ?? false;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = ($role instanceof \Illuminate\Database\Eloquent\Model) ? (string) $role->getKey() : (is_scalar($role) ? (string) $role : '');

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$roleId],
            'guard_name' => ['sometimes', 'string', 'max:255'],
            'display_name' => ['nullable', 'array'],
            'display_name.*' => ['string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['string', 'max:1000'],
            'is_system' => ['sometimes', 'boolean'],
            'is_editable' => ['sometimes', 'boolean'],
            'is_assignable' => ['sometimes', 'boolean'],
        ];
    }
}
