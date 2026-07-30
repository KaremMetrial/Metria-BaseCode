<?php

declare(strict_types=1);

namespace Modules\Currency\Infrastructure\Persistence\Repositories;

use Modules\Shared\Infrastructure\Persistence\BaseRepository;
use Modules\Currency\Domain\Repositories\ExchangeRateRepositoryInterface;
use Modules\Currency\Domain\Models\CurrencyExchangeRate;
use DateTimeInterface;

/**
 * @extends BaseRepository<CurrencyExchangeRate>
 */
class ExchangeRateRepository extends BaseRepository implements ExchangeRateRepositoryInterface
{
    public function __construct(CurrencyExchangeRate $model)
    {
        parent::__construct($model);
    }

    public function getActiveRate(string $currencyCode, DateTimeInterface $at): ?CurrencyExchangeRate
    {
        /** @var CurrencyExchangeRate|null */
        return $this->query()
            ->where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '<=', $at)
            ->where('expires_at', '>', $at)
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function store(array $data): CurrencyExchangeRate
    {
        /** @var CurrencyExchangeRate */
        return $this->create($data);
    }

    public function updateExpiresAt(string $id, DateTimeInterface $expiresAt): void
    {
        $this->query()->where('id', $id)->update([
            'expires_at' => $expiresAt,
        ]);
    }

    public function findLatestRateBefore(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate
    {
        /** @var CurrencyExchangeRate|null */
        return $this->query()
            ->where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '<', $effectiveAt)
            ->orderBy('effective_at', 'desc')
            ->first();
    }

    public function findFirstRateAfter(string $currencyCode, DateTimeInterface $effectiveAt): ?CurrencyExchangeRate
    {
        /** @var CurrencyExchangeRate|null */
        return $this->query()
            ->where('currency_code', strtoupper($currencyCode))
            ->where('effective_at', '>', $effectiveAt)
            ->orderBy('effective_at', 'asc')
            ->first();
    }
}
