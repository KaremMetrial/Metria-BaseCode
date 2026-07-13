<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\Models\User;
use PHPUnit\Framework\Assert;

trait AuthorizationAssertions
{
    /**
     * Assert that the user's effective permissions match the exact expected array.
     */
    protected function assertEffectivePermissionsMatch(User $user, array $expectedPermissions): void
    {
        $actualPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        
        sort($expectedPermissions);
        sort($actualPermissions);

        Assert::assertEquals(
            $expectedPermissions,
            $actualPermissions,
            "Effective permissions do not match. Expected: [" . implode(', ', $expectedPermissions) . "], Actual: [" . implode(', ', $actualPermissions) . "]"
        );
    }
}
