<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Neues Feld "type" (z. B. 'media', 'roter_faden', 'avatar', ...)
            if (!Schema::hasColumn('files', 'type')) {
                $table->string('type', 50)
                      ->default('default')
                      ->index()
                      ->after('mime_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
