<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $personal_access_token_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $device_fingerprint
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property User|null $user
 * @property PersonalAccessToken|null $token
 */
class UserSession extends Model
{
    use HasUuid;

    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function revoke(): void
    {
        $this->token?->delete();
        $this->delete();
    }
}
