<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Territory\Models\City;
use App\Domain\Territory\Models\Country;
use App\Domain\Territory\Models\District;
use App\Domain\Territory\Models\Governorate;
use App\Domain\Territory\Models\Zone;
use Illuminate\Database\Seeder;

class TerritorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Egypt (EG)
        $egypt = Country::firstOrCreate(
            ['iso_code_2' => 'EG'],
            [
                'name' => ['en' => 'Egypt', 'ar' => 'مصر'],
                'iso_code_3' => 'EGY',
                'phone_code' => '+20',
                'currency' => 'EGP',
                'is_active' => true,
            ]
        );

        $cairoGov = Governorate::firstOrCreate(
            ['country_id' => $egypt->id, 'code' => 'CAI'],
            [
                'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
                'is_active' => true,
            ]
        );

        $newCairo = City::firstOrCreate(
            ['governorate_id' => $cairoGov->id, 'postal_code' => '11835'],
            [
                'name' => ['en' => 'New Cairo', 'ar' => 'القاهرة الجديدة'],
                'latitude' => 30.0300,
                'longitude' => 31.4700,
                'is_active' => true,
            ]
        );

        District::firstOrCreate(
            ['city_id' => $newCairo->id, 'name' => ['en' => 'Fifth Settlement', 'ar' => 'التجمع الخامس']],
            [
                'latitude' => 30.0200,
                'longitude' => 31.4600,
                'is_active' => true,
            ]
        );

        Zone::firstOrCreate(
            ['city_id' => $newCairo->id, 'code' => 'new_cairo_logistics'],
            [
                'name' => ['en' => 'New Cairo Logistics Zone', 'ar' => 'نطاق توصيل القاهرة الجديدة'],
                'polygon_coordinates' => [
                    [30.0000, 31.4000],
                    [30.0600, 31.4000],
                    [30.0600, 31.5000],
                    [30.0000, 31.5000],
                ],
                'is_active' => true,
            ]
        );

        // 2. Saudi Arabia (SA)
        $saudi = Country::firstOrCreate(
            ['iso_code_2' => 'SA'],
            [
                'name' => ['en' => 'Saudi Arabia', 'ar' => 'المملكة العربية السعودية'],
                'iso_code_3' => 'SAU',
                'phone_code' => '+966',
                'currency' => 'SAR',
                'is_active' => true,
            ]
        );

        $riyadhGov = Governorate::firstOrCreate(
            ['country_id' => $saudi->id, 'code' => 'RUH'],
            [
                'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
                'is_active' => true,
            ]
        );

        $riyadhCity = City::firstOrCreate(
            ['governorate_id' => $riyadhGov->id, 'postal_code' => '11564'],
            [
                'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
                'latitude' => 24.7136,
                'longitude' => 46.6753,
                'is_active' => true,
            ]
        );

        District::firstOrCreate(
            ['city_id' => $riyadhCity->id, 'name' => ['en' => 'Al-Olaya', 'ar' => 'العليا']],
            [
                'latitude' => 24.7000,
                'longitude' => 46.6800,
                'is_active' => true,
            ]
        );

        Zone::firstOrCreate(
            ['city_id' => $riyadhCity->id, 'code' => 'riyadh_central'],
            [
                'name' => ['en' => 'Riyadh Central Zone', 'ar' => 'نطاق الرياض المركزي'],
                'polygon_coordinates' => [
                    [24.6000, 46.5000],
                    [24.8000, 46.5000],
                    [24.8000, 46.8000],
                    [24.6000, 46.8000],
                ],
                'is_active' => true,
            ]
        );
    }
}
