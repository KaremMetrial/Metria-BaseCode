<?php

declare(strict_types=1);

namespace App\Domain\Governance\Observers;

use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->log(
            action: $this->action($model, 'created'),
            auditable: $model,
            newValues: $this->clean($model, $model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model, $model->getChanges());

        if ($changes === []) {
            return;
        }

        $original = array_intersect_key($model->getOriginal(), $changes);

        $this->logger->log(
            action: $this->action($model, 'updated'),
            auditable: $model,
            oldValues: $original,
            newValues: $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $this->logger->log(
            action: $this->action($model, 'deleted'),
            auditable: $model,
            oldValues: $this->clean($model, $model->getAttributes()),
        );
    }

    private function action(Model $model, string $verb): string
    {
        return str(class_basename($model))->snake()->toString().'.'.$verb;
    }

    private function clean(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcluded') ? $model->auditExcluded() : [];

        return array_diff_key($attributes, array_flip($excluded));
    }
}
