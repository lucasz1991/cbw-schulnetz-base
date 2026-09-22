<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaching_contracts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('uvs_contract_id')->unique();
            $t->unsignedInteger('institut_id')->index();
            $t->string('uvs_person_id');
            $t->string('beratung_id');
            $t->string('teilnehmer_id')->nullable();
            $t->string('massnahme_id')->nullable();
            $t->unsignedBigInteger('participant_person_id')->nullable()->index();
            $t->unsignedBigInteger('tutor_person_id')->nullable()->index();
            $t->unsignedBigInteger('course_id')->nullable()->unique();
            $t->string('title');
            $t->unsignedInteger('agreed_minutes');
            $t->unsignedSmallInteger('unit_minutes');
            $t->string('contract_version', 64);
            $t->string('contract_status', 24)->default('active');
            $t->date('valid_from')->nullable();
            $t->date('valid_until')->nullable();
            $t->date('cancelled_on')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedBigInteger('confirmed_plan_id')->nullable();
            $t->timestamp('last_imported_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamps();
        });
        Schema::create('coaching_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coaching_contract_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision');
            $t->string('contract_version', 64);
            $t->unsignedBigInteger('tutor_person_id');
            $t->unsignedBigInteger('participant_person_id');
            $t->unsignedBigInteger('created_by');
            $t->json('items');
            $t->string('status', 24)->default('proposed');
            $t->timestamp('tutor_confirmed_at')->nullable();
            $t->timestamp('participant_confirmed_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['coaching_contract_id', 'revision']);
        });
        Schema::create('coaching_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coaching_contract_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('user_id');
            $t->unsignedInteger('plan_revision');
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('coaching_outbox', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('coaching_contract_id')->constrained()->restrictOnDelete();
            $t->string('type', 32);
            $t->json('payload');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable()->index();
            $t->timestamp('available_at')->nullable();
            $t->string('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('coaching_assignment_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coaching_contract_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('tutor_person_id');
            $t->unsignedBigInteger('assigned_by');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('coaching_contracts') && \Illuminate\Support\Facades\DB::table('coaching_contracts')->exists()) {
            throw new RuntimeException('Einzelcoaching-Daten vorhanden. Für den Rückbau zuerst sichern; die Pilotfunktion kann per Konfiguration deaktiviert werden.');
        }
        // Never remove signed teaching/report-book records as part of this rollback.
        foreach (['coaching_assignment_history', 'coaching_outbox', 'coaching_messages', 'coaching_plans', 'coaching_contracts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
