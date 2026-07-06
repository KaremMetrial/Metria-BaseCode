<?php

declare(strict_types=1);

namespace App\Core\Abstracts;

use JsonSerializable;

/**
 * Base for immutable DTOs. Extend with readonly promoted constructor
 * properties and add a static fromArray()/fromRequest() named constructor.
 */
abstract class DataTransferObject implements JsonSerializable
{
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
