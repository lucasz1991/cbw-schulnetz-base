<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_appointments', function (Blueprint $table) {
            $table->id();

            // intern | extern
            $table->enum('type', ['intern', 'extern'])->index();

            $table->string('name');                    // Bezeichnung des Prüfungstermins
            $table->decimal('preis', 10, 2)->nullable(); // Preis (optional)
            $table->dateTime('termin')->index();       // Termin/Zeitpunkt

            // Pflicht: 6 Wochen vorher anmelden?
            $table->boolean('pflicht_6w_anmeldung')->default(false)->index();

            $table->timestamps();
            $table->softDeletes(); // optional, praktisch im Admin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_appointments');
    }
};
