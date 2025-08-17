<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('file_pools', function (Blueprint $table) {
            $table->id();

            // morphable Beziehung z. B. zu User, Course, Group etc.
            $table->morphs('filepoolable'); // = filepoolable_id + filepoolable_type

            $table->string('title');
            $table->string('type')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_pools');
    }
};