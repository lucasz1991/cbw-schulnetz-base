<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // UVS-/Maßnahmen-ID kann alphanumerisch sein, deshalb string:
            $table->string('massnahme_id', 50)
                  ->nullable()
                  ->index()
                  ->comment('Referenz auf Bildungsmaßnahme oder Kurs (z. B. UVS-ID)');

            $table->foreignId('course_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

            $table->string('title')->default('Mein Berichtsheft');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            // Ein Berichtsheft pro Teilnehmer & Maßnahme
            $table->unique(['user_id', 'massnahme_id', 'course_id'], 'user_massnahme_course_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_books');
    }
};
