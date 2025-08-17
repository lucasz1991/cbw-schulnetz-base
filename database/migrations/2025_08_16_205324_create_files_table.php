<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            // Morphable Beziehung
            $table->morphs('fileable'); // fileable_id + fileable_type

            // Optional: Gruppierungs-ID (z. B. filepool_id für Materialpools)
            $table->unsignedBigInteger('filepool_id')->nullable()->index();

            // Optional: Ersteller oder Ziel-User
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Datei-Metadaten
            $table->string('name');                // Anzeigename
            $table->string('path');                // Speicherpfad (z. B. in storage/app)
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // in Bytes

            $table->dateTime('expires_at')->nullable();     // Ablaufdatum

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
