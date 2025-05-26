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
        Schema::create('rating_questions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('question_text');
            $table->string('type'); // ← hier als string statt enum
            $table->boolean('is_required')->default(true);
            $table->json('meta')->nullable();
            $table->text('help_text')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('frontend_title')->nullable();
            $table->text('frontend_description')->nullable();
            $table->decimal('weight', 4, 2)->default(1.0);
            $table->json('input_constraints')->nullable();
            $table->boolean('read_only')->default(false);
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_questions');
    }
};
