<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Providers;

use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Policies\WalletPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);
    }
}
