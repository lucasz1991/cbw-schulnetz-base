<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_participant_enrollments', function (Blueprint $t) {
            $t->id();

            // Kern-Relationen
            $t->unsignedSmallInteger('course_id');
            $t->unsignedSmallInteger('person_id');

            // Externe UVS/Altsystem-IDs
            $t->string('teilnehmer_id')->nullable()->index();
            $t->string('tn_baustein_id')->nullable()->index();
            $t->string('baustein_id')->nullable();
            $t->string('klassen_id')->nullable()->index();
            $t->string('termin_id')->nullable();
            $t->string('vtz', 3)->nullable();
            $t->string('kurzbez_ba', 32)->nullable();

            // Status/Meta
            $t->string('status', 24)->nullable();   // z.B. aktiv, geplant, abgebrochen
            $t->boolean('is_active')->default(true);

            // Ergebnisse & Notizen (frei strukturierbar)
            $t->json('results')->nullable();  // Prüfungen, Anwesenheiten, Bewertungen etc.
            $t->json('notes')->nullable();    // Freitext-/Systemnotizen, Historie

            // Sync-/Offline
            $t->json('source_snapshot')->nullable();
            $t->timestamp('source_last_upd')->nullable();
            $t->timestamp('last_synced_at')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->unique(['course_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_participant_enrollments');
    }
};
