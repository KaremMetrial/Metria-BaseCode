<?php

declare(strict_types=1);

namespace Modules\Territory\Infrastructure\Persistence\Repositories;

use Modules\Shared\Infrastructure\Persistence\BaseRepository;
use Modules\Territory\Domain\Models\Governorate;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Governorate>
 */
class GovernorateRepository extends BaseRepository
{
    public function __construct(Governorate $model)
    {
        parent::__construct($model);
    }

    public function getActiveByCountry(string $countryId, ?string $tenantId = null): Collection
    {
        return $this->query($tenantId)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->get();
    }
}
