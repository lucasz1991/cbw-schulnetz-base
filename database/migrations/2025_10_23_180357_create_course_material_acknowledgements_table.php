<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('course_material_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('person_id'); // Teilnehmer (people.id)
            $table->unsignedBigInteger('enrollment_id')->nullable(); // course_participant_enrollments.id
            $table->timestamp('acknowledged_at');
            $table->string('signature_path')->nullable();   // private Storage: PNG
            $table->string('signature_hash', 64)->nullable(); // sha256 zur Integrität
            $table->json('meta')->nullable(); // z.B. user_agent, ip, materials_snapshot
            $table->timestamps();

            $table->index(['course_id','person_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('course_material_acknowledgements');
    }
};
