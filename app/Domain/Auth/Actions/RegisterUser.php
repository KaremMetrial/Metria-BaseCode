<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Core\Events\EventBus;
use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single-responsibility action: create the account, assign the default role,
 * publish the domain event (in-process listeners + outbox for external
 * consumers). Everything inside one transaction.
 */
class RegisterUser
{
    public function __construct(private readonly EventBus $events) {}

    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'locale' => $data['locale'] ?? app()->getLocale(),
            ]);

            $user->assignRole('customer');

            $this->events->publish(new UserRegistered($user));

            return $user;
        });
    }
}
