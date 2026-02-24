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
        Schema::table('course_ratings', function (Blueprint $table) {
            $table->boolean('skip_course_rating')->default(false)->after('is_anonymous');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_ratings', function (Blueprint $table) {
            $table->dropColumn('skip_course_rating');
        });
    }
};

