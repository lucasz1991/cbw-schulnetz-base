<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_days', function (Blueprint $table) {

            // tinyint-Status für Notes
            $table->tinyInteger('note_status')
                  ->default(0)
                  ->after('notes');

            // JSON Settings (z. B. für spätere Flags/Konfigurationen)
            $table->json('settings')
                  ->nullable()
                  ->after('note_status');
        });

        // Bestehende Datensätze prüfen:
        // Wenn NOTES nicht leer → note_status = 1
        DB::table('course_days')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->update([
                'note_status' => 1
            ]);
    }

    public function down(): void
    {
        Schema::table('course_days', function (Blueprint $table) {
            $table->dropColumn(['note_status', 'settings']);
        });
    }
};
