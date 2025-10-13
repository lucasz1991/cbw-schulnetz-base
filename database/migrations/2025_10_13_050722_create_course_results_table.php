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
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id'); // Verknüpft zu persons.id oder deiner Personentabelle
            $table->string('result', 50)->nullable(); // z. B. Note oder Punktzahl
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_results');
    }
};
