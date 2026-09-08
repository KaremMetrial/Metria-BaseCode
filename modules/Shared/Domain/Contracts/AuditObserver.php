<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent lifecycle observer for models using the Shared `Auditable` trait.
 * Implemented by Governance's AuditableObserver; the trait resolves this
 * contract from the container instead of importing Governance directly.
 */
interface AuditObserver
{
    public function created(Model $model): void;

    public function updated(Model $model): void;

    public function deleted(Model $model): void;
}
