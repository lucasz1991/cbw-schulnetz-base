<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->json('style')
                ->nullable()
                ->default(json_encode([
                    'font_color' => '#ffffff',
                    'border_color' => '#000000',
                    'bg_color' => '#dddddd'
                ]))
                ->after('description');
        });

        // style.json updaten mit Werten aus der color-Spalte
        DB::statement("
            UPDATE insurances
            SET style = JSON_OBJECT(
                'font_color', '#ffffff',
                'border_color', '#000000',
                'bg_color', COALESCE(color, '#dddddd')
            )
        ");

        // order_column mit id vorbelegen
        DB::statement("
            UPDATE insurances
            SET order_column = id
        ");
    }

    public function down(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->dropColumn('style');
        });
    }
};
