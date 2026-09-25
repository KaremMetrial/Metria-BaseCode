<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Infrastructure\Database\Factories\UserFactory;
use Modules\Media\Domain\Models\Media;
use Modules\Shared\Infrastructure\Traits\Auditable;
use Modules\Shared\Infrastructure\Traits\BelongsToTenant;
use Modules\Shared\Infrastructure\Traits\HasUuid;
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
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $two_factor_recovery_codes
 * @property string|null $locale
 * @property bool $is_active
 * @property array|null $preferences
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, FcmDeviceToken> $fcmDeviceTokens
 * @property Collection<int, UserSession> $sessions
 * @property Collection<int, UserSocialIdentity> $socialIdentities
 * @property Media|null $avatar
 */
class User extends Authenticatable implements HasLocalePreference
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

    /**
     * Makes every queued Notification (mail/SMS/FCM) render in this user's
     * own saved locale instead of the app's fixed default — a queue worker
     * has no request context, so without this every notification would go
     * out in whatever locale happened to be app()->getLocale() at boot.
     */
    public function preferredLocale(): string
    {
        $fallback = config('localization.fallback', 'en');

        return is_string($this->locale) && $this->locale !== ''
            ? $this->locale
            : (is_string($fallback) ? $fallback : 'en');
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
