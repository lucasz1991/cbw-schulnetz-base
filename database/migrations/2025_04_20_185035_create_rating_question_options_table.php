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
        Schema::create('rating_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_question_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // Sichtbarer Text im Formular
            $table->string('value'); // Technischer Wert, der gespeichert wird
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_column')->default(0); // Sortierung
            $table->json('visibility_condition')->nullable(); // Optional sichtbar je nach Antwortlogik
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_question_options');
    }
};
