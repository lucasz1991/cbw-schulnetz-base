<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coaching_contracts', function (Blueprint $table) {
            $table->string('uvs_tutor_person_id', 13)->nullable();
            $table->unsignedBigInteger('tutor_notified_user_id')->nullable();
            $table->timestamp('tutor_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('coaching_contracts')->exists()) {
            throw new RuntimeException('Dozentenzuordnung und Zustellnachweise bleiben erhalten. Vor einem Rückbau die Coaching-Daten sichern.');
        }
        Schema::table('coaching_contracts', fn (Blueprint $table) => $table->dropColumn([
            'uvs_tutor_person_id', 'tutor_notified_user_id', 'tutor_notified_at',
        ]));
    }
};
