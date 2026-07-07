<?php

declare(strict_types=1);

namespace Tests\Feature\Territory;

use App\Domain\Territory\Events\ZoneStatusChanged;
use App\Domain\Territory\Models\Country;
use App\Domain\Territory\Services\CountryService;
use App\Domain\Territory\Services\ZoneService;
use Database\Seeders\TerritorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TerritoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_territory_hierarchy_retrieval_endpoints(): void
    {
        $this->seed(TerritorySeeder::class);

        $response = $this->getJson('/api/v1/territories/countries');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));

        $egypt = Country::where('iso_code_2', 'EG')->firstOrFail();
        $govResponse = $this->getJson("/api/v1/territories/countries/{$egypt->id}/governorates");
        $govResponse->assertStatus(200);
        $this->assertCount(1, $govResponse->json('data'));
        $this->assertSame('CAI', $govResponse->json('data.0.code'));
    }

    public function test_point_in_polygon_zone_resolution(): void
    {
        $this->seed(TerritorySeeder::class);

        // Point inside New Cairo Logistics Zone (bounding box [30.00, 31.40] to [30.06, 31.50])
        $insideResponse = $this->postJson('/api/v1/territories/zones/resolve', [
            'latitude' => 30.0300,
            'longitude' => 31.4700,
        ]);
        $insideResponse->assertStatus(200);
        $insideResponse->assertJsonPath('data.code', 'new_cairo_logistics');

        // Point outside any defined zone
        $outsideResponse = $this->postJson('/api/v1/territories/zones/resolve', [
            'latitude' => 0.0000,
            'longitude' => 0.0000,
        ]);
        $outsideResponse->assertStatus(404);
        $outsideResponse->assertJsonPath('success', false);
    }

    public function test_territory_service_redis_caching(): void
    {
        $this->seed(TerritorySeeder::class);

        Cache::flush();
        $this->assertFalse(Cache::has('territory:countries'));

        app(CountryService::class)->getCountries();
        $this->assertTrue(Cache::has('territory:countries'));
    }

    public function test_zone_creation_and_status_changed_event(): void
    {
        Event::fake([ZoneStatusChanged::class]);

        $zoneService = app(ZoneService::class);
        $zone = $zoneService->createZone([
            'name' => ['en' => 'Test Zone', 'ar' => 'نطاق تجريبي'],
            'code' => 'test_zone',
            'polygon_coordinates' => [[0, 0], [0, 1], [1, 1], [1, 0]],
            'is_active' => true,
        ]);

        Event::assertDispatched(ZoneStatusChanged::class, function ($event) use ($zone) {
            return $event->zone->id === $zone->id && $event->statusReason === 'created';
        });

        $zoneService->updateZoneStatus($zone, false, 'maintenance');

        Event::assertDispatched(ZoneStatusChanged::class, function ($event) use ($zone) {
            return $event->zone->id === $zone->id && $event->statusReason === 'maintenance';
        });
    }
}
