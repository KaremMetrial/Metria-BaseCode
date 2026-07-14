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
        $nameVal = $data['name'] ?? '';
        $displayNameVal = $data['display_name'] ?? null;
        $descriptionVal = $data['description'] ?? null;
        $priorityVal = $data['priority'] ?? 100;
        $guardNameVal = $data['guard_name'] ?? null;

        return new self(
            name: is_string($nameVal) ? $nameVal : '',
            displayName: is_array($displayNameVal) ? $displayNameVal : null,
            description: is_array($descriptionVal) ? $descriptionVal : null,
            priority: is_numeric($priorityVal) ? (int) $priorityVal : 100,
            isSystem: (bool) ($data['is_system'] ?? false),
            isEditable: (bool) ($data['is_editable'] ?? true),
            isAssignable: (bool) ($data['is_assignable'] ?? true),
            guardName: is_string($guardNameVal) ? $guardNameVal : null,
        );
    }
}
