<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // → beide Strings, nullable (UVS liefert nicht immer Werte)
            if (!Schema::hasColumn('persons', 'teilnehmer_nr')) {
                $table->string('teilnehmer_nr')->nullable()->after('person_nr');
            }

            if (!Schema::hasColumn('persons', 'teilnehmer_id')) {
                $table->string('teilnehmer_id')->nullable()->after('teilnehmer_nr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            if (Schema::hasColumn('persons', 'teilnehmer_nr')) {
                $table->dropColumn('teilnehmer_nr');
            }

            if (Schema::hasColumn('persons', 'teilnehmer_id')) {
                $table->dropColumn('teilnehmer_id');
            }
        });
    }
};
