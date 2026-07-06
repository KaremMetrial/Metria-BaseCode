<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\TerritorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_territory_filter_search_across_any_language_and_code(): void
    {
        $this->seed(TerritorySeeder::class);

        // 1. Search in English
        $enResponse = $this->getJson('/api/v1/territories/countries?search=Egypt');
        $enResponse->assertStatus(200);
        $this->assertCount(1, $enResponse->json('data'));
        $this->assertSame('EG', $enResponse->json('data.0.iso_code_2'));

        // 2. Search in Arabic
        $arResponse = $this->getJson('/api/v1/territories/countries?search=مصر');
        $arResponse->assertStatus(200);
        $this->assertCount(1, $arResponse->json('data'));
        $this->assertSame('EG', $arResponse->json('data.0.iso_code_2'));

        // 3. Search by ISO code / code
        $codeResponse = $this->getJson('/api/v1/territories/countries?search=EGY');
        $codeResponse->assertStatus(200);
        $this->assertCount(1, $codeResponse->json('data'));
        $this->assertSame('EG', $codeResponse->json('data.0.iso_code_2'));
    }

    public function test_territory_filter_sorting_and_active_status(): void
    {
        $this->seed(TerritorySeeder::class);

        // Sort descending by iso_code_2 (SA should come before EG)
        $response = $this->getJson('/api/v1/territories/countries?filter[is_active]=true&sort=-iso_code_2');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('SA', $response->json('data.0.iso_code_2'));
        $this->assertSame('EG', $response->json('data.1.iso_code_2'));
    }
}
