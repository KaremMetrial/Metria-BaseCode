<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WalletTransactionType: string
{
    case Credit = 'credit';        // funds in
    case Debit = 'debit';          // funds out
    case Hold = 'hold';            // escrow: lock part of the balance
    case Release = 'release';      // escrow: unlock a hold back to available
    case CaptureHold = 'capture';  // escrow: held funds leave the wallet
}
