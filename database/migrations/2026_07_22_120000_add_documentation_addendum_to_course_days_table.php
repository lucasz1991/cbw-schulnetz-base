<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_days', function (Blueprint $table) {
            $table->longText('documentation_addendum')->nullable()->after('notes');
            $table->unsignedTinyInteger('documentation_addendum_status')->default(0)->after('documentation_addendum');
            $table->foreignId('documentation_addendum_saved_by_user_id')
                ->nullable()
                ->after('documentation_addendum_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('documentation_addendum_saved_at')->nullable()->after('documentation_addendum_saved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('course_days', function (Blueprint $table) {
            $table->dropForeign(['documentation_addendum_saved_by_user_id']);
            $table->dropColumn([
                'documentation_addendum',
                'documentation_addendum_status',
                'documentation_addendum_saved_by_user_id',
                'documentation_addendum_saved_at',
            ]);
        });
    }
};
