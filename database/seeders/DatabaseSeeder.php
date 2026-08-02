<?php

declare(strict_types=1);

namespace Database\Seeders;

use Modules\RBAC\Infrastructure\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Territory\Infrastructure\Database\Seeders\TerritorySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            TerritorySeeder::class,
        ]);
    }
}
