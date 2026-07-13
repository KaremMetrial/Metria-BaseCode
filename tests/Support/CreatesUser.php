<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\Models\User;
use Illuminate\Support\Str;

trait CreatesUser
{
    /**
     * Create a mock user, optionally attaching them to a specific tenant.
     */
    protected function createUser(?string $tenantId = null, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'tenant_id' => $tenantId ?? (string) Str::uuid(),
        ], $attributes));
    }
}
