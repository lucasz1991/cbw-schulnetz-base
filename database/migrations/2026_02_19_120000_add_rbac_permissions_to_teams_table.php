<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->json('rbac_permissions')->nullable()->after('personal_team');
        });

        $settingsRow = DB::table('settings')
            ->where('type', 'rbac')
            ->where('key', 'team_permissions')
            ->first();

        if ($settingsRow && $settingsRow->value) {
            $decoded = json_decode((string) $settingsRow->value, true);
            if (is_array($decoded)) {
                foreach ($decoded as $teamId => $permissions) {
                    if (! is_array($permissions)) {
                        continue;
                    }

                    DB::table('teams')
                        ->where('id', (int) $teamId)
                        ->update([
                            'rbac_permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('rbac_permissions');
        });
    }
};
