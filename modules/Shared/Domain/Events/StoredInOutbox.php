<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Events;

/**
 * Marker interface. Events implementing it are persisted to the transactional
 * outbox by EventBus::publish() and delivered by `php artisan outbox:publish`
 * only after the surrounding DB transaction commits — guaranteeing external
 * consumers never hear about state that was rolled back.
 */
interface StoredInOutbox {}
