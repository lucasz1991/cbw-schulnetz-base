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
        Schema::create('insurance_type_insurance_subtype', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurance_subtype_id')->constrained()->onDelete('cascade');
            $table->decimal('weight', 4, 2)->default(1.0);
            $table->unsignedInteger('order_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_type_insurance_subtype');
    }
};
