<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary(); // ISO-4217 code (e.g. 'USD')
            $table->json('name'); // {"en": "US Dollar", "ar": "دولار أمريكي"}
            $table->json('symbol'); // {"en": "$", "ar": "$"}
            $table->unsignedTinyInteger('minor_units')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            Schema::table('currencies', function (Blueprint $table) {
                $table->tinyInteger('is_default_unique')
                    ->virtualAs('case when is_default = 1 then 1 else null end')
                    ->nullable();
                $table->unique('is_default_unique', 'unique_default_currency');
            });
        } else {
            DB::statement('CREATE UNIQUE INDEX unique_default_currency ON currencies (is_default) WHERE is_default = 1;');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
