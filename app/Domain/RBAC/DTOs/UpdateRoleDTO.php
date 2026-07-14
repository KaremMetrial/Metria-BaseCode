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
        $nameVal = $data['name'] ?? null;
        $displayNameVal = $data['display_name'] ?? null;
        $descriptionVal = $data['description'] ?? null;
        $priorityVal = $data['priority'] ?? null;
        $guardNameVal = $data['guard_name'] ?? null;

        return new self(
            name: is_string($nameVal) ? $nameVal : null,
            displayName: is_array($displayNameVal) ? $displayNameVal : null,
            description: is_array($descriptionVal) ? $descriptionVal : null,
            priority: is_numeric($priorityVal) ? (int) $priorityVal : null,
            isSystem: isset($data['is_system']) ? (bool) $data['is_system'] : null,
            isEditable: isset($data['is_editable']) ? (bool) $data['is_editable'] : null,
            isAssignable: isset($data['is_assignable']) ? (bool) $data['is_assignable'] : null,
            guardName: is_string($guardNameVal) ? $guardNameVal : null,
        );
    }
}
