<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Core\Traits\HasUuid;
use App\Domain\Governance\Traits\Auditable;
use App\Domain\Media\Models\Media;
use App\Domain\Wallet\Models\Wallet;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $password
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret
 * @property string|null $mfa_backup_codes
 * @property string|null $two_factor_secret
 * @property \Illuminate\Support\Carbon|null $two_factor_confirmed_at
 * @property string|null $two_factor_recovery_codes
 * @property string|null $locale
 * @property bool $is_active
 * @property array|null $preferences
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Wallet|null $wallet
 * @property \Illuminate\Database\Eloquent\Collection<int, FcmDeviceToken> $fcmDeviceTokens
 * @property \Illuminate\Database\Eloquent\Collection<int, UserSession> $sessions
 * @property \Illuminate\Database\Eloquent\Collection<int, UserSocialIdentity> $socialIdentities
 * @property Media|null $avatar
 */
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
        'email_verified_at', 'phone_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected string $guard_name = 'web';

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

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function socialIdentities(): HasMany
    {
        return $this->hasMany(UserSocialIdentity::class);
    }

    public function hasMfaEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && ! empty($this->two_factor_confirmed_at);
    }

    public function hasPasswordSet(): bool
    {
        return ! empty($this->password);
    }

    public function canUnlinkIdentity(): bool
    {
        return $this->hasPasswordSet() || $this->socialIdentities()->count() > 1;
    }

    public function updateFcmDeviceToken(string $token, ?string $deviceId = null, ?string $deviceName = null, ?string $platform = null): FcmDeviceToken
    {
        FcmDeviceToken::query()->where('device_token', $token)->where('user_id', '!=', $this->id)->delete();

        /** @var FcmDeviceToken $tokenModel */
        $tokenModel = $this->fcmDeviceTokens()->updateOrCreate(
            ['device_token' => $token],
            [
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'platform' => $platform,
            ]
        );

        return $tokenModel;
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('purpose', 'avatar');
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
