<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $device_token
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $platform
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User|null $user
 */
class FcmDeviceToken extends Model
{
    use HasUuid;

    protected $table = 'fcm_device_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'device_token',
        'device_id',
        'device_name',
        'platform',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
