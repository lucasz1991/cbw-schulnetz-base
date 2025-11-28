<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Neue Spalten für Attendance-Sync hinzufügen.
     */
    public function up(): void
    {
        Schema::table('course_days', function (Blueprint $table) {
            // Wann wurden die Attendance-Daten lokal zuletzt geändert?
            $table->timestamp('attendance_updated_at')
                ->nullable()
                ->after('attendance_data')
                ->index();

            // Wann wurde zuletzt mit der UVS-API synchronisiert?
            $table->timestamp('attendance_last_synced_at')
                ->nullable()
                ->after('attendance_updated_at')
                ->index();
        });
    }

    /**
     * Spalten wieder entfernen (Rollback).
     */
    public function down(): void
    {
        Schema::table('course_days', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_updated_at',
                'attendance_last_synced_at',
            ]);
        });
    }
};
