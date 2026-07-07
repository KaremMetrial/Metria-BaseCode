<?php

declare(strict_types=1);

namespace App\Core\Support;

interface CurrencyRegistryResolver
{
    /**
     * Resolve the number of minor units (decimal places) for a given ISO currency code.
     *
     * @param  string  $currency  ISO-4217 currency code (e.g., 'USD', 'EGP', 'BHD')
     * @return int The number of minor units (e.g., 2 for USD, 3 for BHD, 0 for JPY)
     */
    public function minorUnitsFor(string $currency): int;
}
