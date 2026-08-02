<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Providers;

use Modules\Auth\Infrastructure\Console\PruneExpiredTokens;
use Modules\Auth\Domain\Events\AccountLockedOut;
use Modules\Auth\Domain\Events\AllSessionsRevoked;
use Modules\Auth\Domain\Events\AuthMethodBlocked;
use Modules\Auth\Domain\Events\MfaDisabled;
use Modules\Auth\Domain\Events\MfaEnabled;
use Modules\Auth\Domain\Events\MfaVerified;
use Modules\Auth\Domain\Events\OtpGenerated;
use Modules\Auth\Domain\Events\PasswordResetRequested;
use Modules\Auth\Domain\Events\PasswordResetSuccessfully;
use Modules\Auth\Domain\Events\SocialIdentityLinked;
use Modules\Auth\Domain\Events\SocialIdentityUnlinked;
use Modules\Auth\Domain\Events\UserLoggedIn;
use Modules\Auth\Domain\Events\UserLoggedInByOtp;
use Modules\Auth\Domain\Events\UserLoggedInByProvider;
use Modules\Auth\Domain\Events\UserRegisteredByOtp;
use Modules\Auth\Domain\Events\UserSessionRevoked;
use Modules\Auth\Infrastructure\Listeners\AuditSecurityEvent;
use Modules\Auth\Infrastructure\Listeners\NotifyPasswordChanged;
use Modules\Auth\Infrastructure\Listeners\NotifySocialAccountLinked;
use Modules\Auth\Infrastructure\Listeners\ProvisionUserDefaults;
use Modules\Auth\Infrastructure\Listeners\SendLoginAlert;
use Modules\Auth\Infrastructure\Listeners\SendOtpNotification;
use Modules\Auth\Domain\Models\FcmDeviceToken;
use Modules\Auth\Domain\Models\User;
use Modules\Auth\Domain\Models\UserSession;
use Modules\Auth\Domain\Models\UserSocialIdentity;
use Modules\Auth\Presentation\Policies\FcmDeviceTokenPolicy;
use Modules\Auth\Presentation\Policies\UserPolicy;
use Modules\Auth\Presentation\Policies\UserSessionPolicy;
use Modules\Auth\Presentation\Policies\UserSocialIdentityPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auth_features.php', 'auth_features');
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(UserSession::class, UserSessionPolicy::class);
        Gate::policy(UserSocialIdentity::class, UserSocialIdentityPolicy::class);
        Gate::policy(FcmDeviceToken::class, FcmDeviceTokenPolicy::class);

        Event::listen(OtpGenerated::class, SendOtpNotification::class);
        Event::listen(UserRegisteredByOtp::class, ProvisionUserDefaults::class);
        Event::listen(UserLoggedIn::class, SendLoginAlert::class);
        Event::listen(UserLoggedInByOtp::class, SendLoginAlert::class);
        Event::listen(UserLoggedInByProvider::class, SendLoginAlert::class);
        Event::listen(SocialIdentityLinked::class, NotifySocialAccountLinked::class);
        Event::listen(PasswordResetSuccessfully::class, NotifyPasswordChanged::class);

        // Audit logging for security events
        Event::listen(MfaEnabled::class, AuditSecurityEvent::class);
        Event::listen(MfaDisabled::class, AuditSecurityEvent::class);
        Event::listen(MfaVerified::class, AuditSecurityEvent::class);
        Event::listen(SocialIdentityLinked::class, AuditSecurityEvent::class);
        Event::listen(SocialIdentityUnlinked::class, AuditSecurityEvent::class);
        Event::listen(UserSessionRevoked::class, AuditSecurityEvent::class);
        Event::listen(AllSessionsRevoked::class, AuditSecurityEvent::class);
        Event::listen(PasswordResetRequested::class, AuditSecurityEvent::class);
        Event::listen(PasswordResetSuccessfully::class, AuditSecurityEvent::class);
        Event::listen(AuthMethodBlocked::class, AuditSecurityEvent::class);
        Event::listen(AccountLockedOut::class, AuditSecurityEvent::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneExpiredTokens::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
