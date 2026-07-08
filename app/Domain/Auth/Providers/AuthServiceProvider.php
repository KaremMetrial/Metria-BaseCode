<?php

declare(strict_types=1);

namespace App\Domain\Auth\Providers;

use App\Domain\Auth\Console\PruneExpiredTokens;
use App\Domain\Auth\Events\AccountLockedOut;
use App\Domain\Auth\Events\AllSessionsRevoked;
use App\Domain\Auth\Events\AuthMethodBlocked;
use App\Domain\Auth\Events\MfaDisabled;
use App\Domain\Auth\Events\MfaEnabled;
use App\Domain\Auth\Events\MfaVerified;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Events\PasswordResetRequested;
use App\Domain\Auth\Events\PasswordResetSuccessfully;
use App\Domain\Auth\Events\SocialIdentityLinked;
use App\Domain\Auth\Events\SocialIdentityUnlinked;
use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Events\UserLoggedInByOtp;
use App\Domain\Auth\Events\UserLoggedInByProvider;
use App\Domain\Auth\Events\UserRegisteredByOtp;
use App\Domain\Auth\Events\UserSessionRevoked;
use App\Domain\Auth\Listeners\AuditSecurityEvent;
use App\Domain\Auth\Listeners\NotifyPasswordChanged;
use App\Domain\Auth\Listeners\NotifySocialAccountLinked;
use App\Domain\Auth\Listeners\ProvisionUserDefaults;
use App\Domain\Auth\Listeners\SendLoginAlert;
use App\Domain\Auth\Listeners\SendOtpNotification;
use App\Domain\Auth\Models\FcmDeviceToken;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSession;
use App\Domain\Auth\Models\UserSocialIdentity;
use App\Domain\Auth\Policies\FcmDeviceTokenPolicy;
use App\Domain\Auth\Policies\UserPolicy;
use App\Domain\Auth\Policies\UserSessionPolicy;
use App\Domain\Auth\Policies\UserSocialIdentityPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
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
    }
}
