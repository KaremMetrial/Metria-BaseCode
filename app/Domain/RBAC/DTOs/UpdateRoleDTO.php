<?php

declare(strict_types=1);

namespace App\Domain\RBAC\DTOs;

use App\Core\Abstracts\DataTransferObject;

class UpdateRoleDTO extends DataTransferObject
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?array $displayName = null,
        public readonly ?array $description = null,
        public readonly ?int $priority = null,
        public readonly ?bool $isSystem = null,
        public readonly ?bool $isEditable = null,
        public readonly ?bool $isAssignable = null,
        public readonly ?string $guardName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            isSystem: isset($data['is_system']) ? (bool) $data['is_system'] : null,
            isEditable: isset($data['is_editable']) ? (bool) $data['is_editable'] : null,
            isAssignable: isset($data['is_assignable']) ? (bool) $data['is_assignable'] : null,
            guardName: $data['guard_name'] ?? null,
        );
    }
}
