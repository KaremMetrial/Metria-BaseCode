<?php

declare(strict_types=1);

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $scope_hash
 * @property int|null $response_status
 * @property string|null $response_body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = ['key', 'scope_hash', 'response_status', 'response_body'];
}
