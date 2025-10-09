<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $t) {
            $t->id();

            // Externe Identität (UVS/API)
            $t->string('klassen_id')->unique();   // z.B. u_klasse.klassen_id
            $t->string('termin_id')->nullable(); // termin_id (für Tage)

            // Kontext aus dem Altsystem (optional, aber praktisch zum Filtern)
            $t->unsignedSmallInteger('institut_id')->nullable();
            $t->string('vtz', 3)->nullable();    // z.B. "VTZ"-Kennzeichen
            $t->string('room', 32)->nullable();

            // Anzeige/Meta im Schulnetz
            $t->string('title');               // frei benennbar (Fallback: aus UVS ableiten)
            $t->text('description')->nullable();

            // Grobe Plan-Daten auf Kursebene (Einzeltage kommen in course_days)
            $t->date('planned_start_date')->nullable();
            $t->date('planned_end_date')->nullable();

            // Sync-/Offline-Unterstützung
            $t->json('source_snapshot')->nullable();   // vollständiger Row-Dump der Quelle (Debug)
            $t->timestamp('source_last_upd')->nullable(); 
            $t->string('type')->default('basic');
            $t->json('settings')->nullable(); 
            $t->boolean('is_active')->default(true);

            // Optional: primärer Tutor als Komfort (auf Personenebene, nicht User)
            $t->unsignedSmallInteger('primary_tutor_person_id')->nullable();

            $t->timestamps();
            $t->softDeletes(); // empfehlenswert: Kurse ausblenden statt hart löschen
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
