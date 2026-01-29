<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (!Schema::hasColumn('files', 'disk')) {
                $table->string('disk', 50)
                      ->default('private')
                      ->after('path');
            }
        });

        // Bestehende Datensätze auf Standard setzen
        DB::table('files')
            ->whereNull('disk')
            ->update(['disk' => 'private']);
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'disk')) {
                $table->dropColumn('disk');
            }
        });
    }
};
