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
        Schema::create('course_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Beziehungen / Kontext
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // Teilnehmerbezug (optional bei anonymer Bewertung)
            $table->foreignId('participant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_anonymous')->default(false);

            // Bewertungsfelder (1–5)
            $table->unsignedTinyInteger('kb_1')->nullable();
            $table->unsignedTinyInteger('kb_2')->nullable();
            $table->unsignedTinyInteger('kb_3')->nullable();

            $table->unsignedTinyInteger('sa_1')->nullable();
            $table->unsignedTinyInteger('sa_2')->nullable();
            $table->unsignedTinyInteger('sa_3')->nullable();

            $table->unsignedTinyInteger('il_1')->nullable();
            $table->unsignedTinyInteger('il_2')->nullable();
            $table->unsignedTinyInteger('il_3')->nullable();

            $table->unsignedTinyInteger('do_1')->nullable();
            $table->unsignedTinyInteger('do_2')->nullable();
            $table->unsignedTinyInteger('do_3')->nullable();

            // Freitextnachricht
            $table->string('message', 500)->nullable();

            // Eindeutige Bewertung pro Teilnehmer & Kurs
            $table->unique(['course_id', 'user_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_ratings');
    }
};
