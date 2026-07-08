<?php

declare(strict_types=1);

namespace App\Core\Abstracts;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Base for immutable DTOs. Extend with readonly promoted constructor
 * properties and add a static fromArray()/fromRequest() named constructor.
 */
abstract class DataTransferObject implements Arrayable, JsonSerializable
{
    public function toArray(): array
    {
        $vars = get_object_vars($this);

        return array_map(function ($value) {
            return $this->normalizeValue($value);
        }, $vars);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Arrayable || $value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map([$this, 'normalizeValue'], $value);
        }

        return $value;
    }
}
