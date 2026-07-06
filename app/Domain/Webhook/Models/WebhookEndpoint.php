<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A consumer-registered URL that receives outbox events. `events` is a list
 * of dot-notation names ('payment.succeeded', ...) or ['*'] for everything.
 * The secret signs every delivery so consumers can authenticate us.
 */
class WebhookEndpoint extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasUuid;

    /** Never leak the signing secret through audit logs. */
    protected array $auditExclude = ['secret'];

    protected $fillable = ['tenant_id', 'name', 'url', 'secret', 'events', 'active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'endpoint_id');
    }

    public function listensTo(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }
}
