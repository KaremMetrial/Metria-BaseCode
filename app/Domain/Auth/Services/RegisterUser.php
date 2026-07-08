<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Single-responsibility service action: create the account, assign the default role,
 * publish the domain event (in-process listeners + outbox for external
 * consumers). Everything inside one atomic database transaction.
 */
class RegisterUser
{
    public function __construct(
        private readonly EventBus $events,
        private readonly AuditLogger $audit
    ) {}

    public function __invoke(array $data, ?string $tenantId = null): User
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $user = User::query()->create([
                'tenant_id' => $tenantId ?? ($data['tenant_id'] ?? null),
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? app()->getLocale(),
            ]);

            $user->assignRole('customer');

            $this->events->publish(new UserRegistered($user));
            $this->audit->log('auth.registered', $user, tenantId: $tenantId);

            return $user;
        });
    }
}
