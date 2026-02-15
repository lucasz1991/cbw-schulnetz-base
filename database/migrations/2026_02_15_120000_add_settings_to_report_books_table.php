<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_books', function (Blueprint $table) {
            if (!Schema::hasColumn('report_books', 'settings')) {
                $table->json('settings')->nullable()->after('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_books', function (Blueprint $table) {
            if (Schema::hasColumn('report_books', 'settings')) {
                $table->dropColumn('settings');
            }
        });
    }
};

