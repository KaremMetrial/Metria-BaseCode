<?php

declare(strict_types=1);

namespace Modules\Wallet\Infrastructure\Providers;

use Modules\Wallet\Domain\Enums\WalletTransactionType;
use Modules\Wallet\Domain\Models\Wallet;
use Modules\Wallet\Presentation\Policies\WalletPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\Support\EnumRegistry;

class WalletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);

        EnumRegistry::register('wallet_transaction_type', WalletTransactionType::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
