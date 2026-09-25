<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Contracts;

/**
 * Seam for reading the DB-backed, admin-editable settings Governance owns
 * (PUT /api/v1/governance/settings/{key}) from any module without that
 * module depending on Governance's concrete SettingsService — same pattern
 * as ApprovalGateway/AuditRecorder. Bound to Governance\Infrastructure\
 * Services\SettingsService in GovernanceServiceProvider.
 */
interface RuntimeSettings
{
    public function get(string $key, mixed $default = null): mixed;
}
