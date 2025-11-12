<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_book_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_book_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('course_day_id')
                  ->nullable()
                  ->nullOnDelete();

            $table->date('entry_date');

            $table->longText('text')->nullable();

            $table->unsignedTinyInteger('status')->default(0)
                  ->comment('0 = Entwurf, 1 = Eingereicht, 2 = Geprüft, 3 = Freigegeben');

            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique(['report_book_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_book_entries');
    }
};
