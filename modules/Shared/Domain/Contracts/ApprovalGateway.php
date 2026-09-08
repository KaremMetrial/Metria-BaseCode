<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Contracts;

use Modules\Auth\Domain\Models\User;

/**
 * Maker-checker workflow entry point: records a request for a sensitive
 * action and returns a pending approval record. Implemented by Governance's
 * ApprovalService; consumers (e.g. Payment) depend on this contract instead
 * of importing Governance's concrete service, so they stay extractable
 * without dragging Governance's approval machinery along.
 */
interface ApprovalGateway
{
    /**
     * @param array<string, mixed> $payload
     */
    public function request(string $action, array $payload, User $requester): object;
}
