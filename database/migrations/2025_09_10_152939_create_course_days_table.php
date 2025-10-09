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
        Schema::create('course_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->float('std')->nullable();
            $table->json('day_sessions')->nullable();
            $table->json('attendance_data')->nullable();
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();

            $table->softDeletes();

            $table->unique(['course_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_days');
    }
};
