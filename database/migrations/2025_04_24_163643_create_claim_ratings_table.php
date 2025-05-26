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
        Schema::create('claim_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('insurance_subtype_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurance_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('rating_questionnaire_versions_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('insurance_id')->constrained()->onDelete('cascade');
            $table->json('answers');
            $table->string('status')->default('pending');
            $table->json('attachments')->nullable();
            $table->decimal('rating_score', 3, 2)->nullable();
            $table->text('moderator_comment')->nullable();
            $table->boolean('is_public')->default(false);
            $table->uuid('verification_hash')->unique()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_ratings');
    }
};
