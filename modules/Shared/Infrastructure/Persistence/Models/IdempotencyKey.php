<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property string $scope_hash
 * @property string|null $request_fingerprint
 * @property int|null $response_status
 * @property string|null $response_body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = ['key', 'scope_hash', 'request_fingerprint', 'response_status', 'response_body'];
}
