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
        Schema::create('onboarding_videos', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // Neu
            $table->string('category')->nullable(); 
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            
            $table->integer('duration_seconds')->nullable();
            
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            
            $table->json('settings')->nullable(); 

            $table->string('version')->nullable();
            
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes(); 
        });

    } 

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_videos');
    }
};
