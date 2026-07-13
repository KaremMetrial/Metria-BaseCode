<?php

declare(strict_types=1);

namespace App\Domain\RBAC\DTOs;

use App\Core\Abstracts\DataTransferObject;

class CreateRoleDTO extends DataTransferObject
{
    public function __construct(
        public readonly string $name,
        public readonly ?array $displayName = null,
        public readonly ?array $description = null,
        public readonly int $priority = 100,
        public readonly bool $isSystem = false,
        public readonly bool $isEditable = true,
        public readonly bool $isAssignable = true,
        public readonly ?string $guardName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            priority: (int) ($data['priority'] ?? 100),
            isSystem: (bool) ($data['is_system'] ?? false),
            isEditable: (bool) ($data['is_editable'] ?? true),
            isAssignable: (bool) ($data['is_assignable'] ?? true),
            guardName: $data['guard_name'] ?? null,
        );
    }
}
