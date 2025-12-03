<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            // Verknüpfung zu person.id (wir lassen FK bewusst weg,
            // weil deine UVS-Personentabelle evtl. "person" heißt)
            $table->unsignedBigInteger('person_id');

            // Ergebnis (z.B. Punktzahl). Numeric Logik liegt in der App.
            $table->string('result', 50)->nullable();

            // Menschlich lesbarer Status ("bestanden", "nicht teilgenommen", ...)
            $table->string('status', 200)->nullable();

            // Wer hat zuletzt in Schulnetz geändert?
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // UVS- / Sync-Felder
            $table->unsignedBigInteger('remote_uid')->nullable();
            $table->string('sync_state', 20)->nullable();      // dirty|synced|remote
            $table->date('remote_upd_date')->nullable();

            $table->timestamps();

            // pro Kurs & Person genau ein Datensatz
            $table->unique(['course_id', 'person_id'], 'course_results_course_person_unique');

            $table->index(['course_id', 'remote_uid'], 'cr_course_remote_idx');
            $table->index(['course_id', 'sync_state'], 'cr_course_sync_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_results');
    }
};
