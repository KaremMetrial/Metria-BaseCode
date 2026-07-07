<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use App\Domain\Wallet\Models\Wallet;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable;
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuid;
    use Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password', 'locale',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $guard_name = 'web';

    /** Attributes excluded from audit logs in addition to global masking. */
    protected array $auditExclude = ['password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function fcmDeviceTokens(): HasMany
    {
        return $this->hasMany(FcmDeviceToken::class);
    }

    public function updateFcmDeviceToken(string $token, ?string $deviceId = null, ?string $deviceName = null, ?string $platform = null): FcmDeviceToken
    {
        FcmDeviceToken::query()->where('device_token', $token)->where('user_id', '!=', $this->id)->delete();

        return $this->fcmDeviceTokens()->updateOrCreate(
            ['device_token' => $token],
            [
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'platform' => $platform,
            ]
        );
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
