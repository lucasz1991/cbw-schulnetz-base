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
        Schema::create('rating_questionnaire_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_subtype_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('snapshot'); // kompletter Fragen- & Antwortzustand
            $table->boolean('is_active')->default(true); // aktuell verwendete Version
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rating_questionnaire_versions');
    }
};
