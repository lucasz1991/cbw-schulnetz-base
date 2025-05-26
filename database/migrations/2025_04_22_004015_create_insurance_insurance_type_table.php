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
        Schema::create('insurance_insurance_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurance_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('order_column')->nullable(); // für Sortierung
            $table->timestamps();
            $table->unique(['insurance_id', 'insurance_type_id']);
        });
    }
    
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_insurance_type');
    }
};
