<?php

declare(strict_types=1);

namespace App\Domain\Auth\Providers;

use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Events\UserRegisteredByOtp;
use App\Domain\Auth\Listeners\ProvisionUserDefaults;
use App\Domain\Auth\Listeners\SendOtpNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OtpGenerated::class, SendOtpNotification::class);
        Event::listen(UserRegisteredByOtp::class, ProvisionUserDefaults::class);
    }
}
