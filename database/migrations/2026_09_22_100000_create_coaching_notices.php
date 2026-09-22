<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coaching_contracts', function (Blueprint $t) {
            $t->json('notification_contacts')->nullable();
            $t->string('planning_fingerprint', 64)->nullable();
        });
        Schema::create('coaching_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coaching_contract_id')->constrained()->restrictOnDelete();
            $t->string('event_key', 64)->unique();
            $t->string('kind', 40);
            $t->string('recipient_role', 16);
            $t->string('uvs_person_id');
            $t->json('content');
            $t->unsignedBigInteger('message_id')->nullable();
            $t->timestamp('mail_sent_at')->nullable();
            $t->timestamp('dismissed_at')->nullable();
            $t->timestamp('available_at')->nullable()->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('last_error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('coaching_notices')->exists()) {
            throw new RuntimeException('Versandnachweise vorhanden; zuerst sichern. Deaktivierung ist in den Einstellungen möglich.');
        }
        Schema::dropIfExists('coaching_notices');
        Schema::table('coaching_contracts', fn (Blueprint $t) => $t->dropColumn(['notification_contacts', 'planning_fingerprint']));
    }
};
