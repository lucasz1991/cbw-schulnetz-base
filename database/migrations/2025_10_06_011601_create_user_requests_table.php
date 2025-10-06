<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();

            // Relation
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Klassifizierung / Basis
            // Typen: makeup (interne Nachprüfung), external_makeup (externe), absence (Fehlzeit), general (sonstiges)
            $table->string('type', 40)->default('general');

            // Personen-/Klassendaten (optional)
            $table->string('class_code', 32)->nullable();        // z. B. "INF23A"
            $table->string('institute', 64)->nullable();         // z. B. "Köln"
            $table->string('participant_no', 32)->nullable();    // z. B. "0000007"

            // Titel / Freitext
            $table->string('title', 200)->nullable();
            $table->text('message')->nullable();

            // Zeiträume / Termine
            $table->date('date_from')->nullable();               // z. B. Fehlzeit-Datum
            $table->date('date_to')->nullable();                 // optionaler Zeitraum
            $table->date('original_exam_date')->nullable();      // ursprüngliche Prüfung
            $table->dateTime('scheduled_at')->nullable();        // geplanter Nachprüfungstermin

            // Prüfungsbezogene Felder
            $table->string('module_code', 32)->nullable();       // „Baustein“ (z. B. "B123")
            $table->string('instructor_name', 120)->nullable();

            // Abwesenheitsspezifisch
            $table->boolean('full_day')->default(false);         // ganztägig gefehlt
            $table->time('time_arrived_late')->nullable();       // später gekommen
            $table->time('time_left_early')->nullable();         // früher gegangen

            // Gründe / Gebühren
            // reason z. B.: unter51 | krankMitAtest | krankOhneAtest | abw_wichtig | abw_unwichtig | zert_faild
            $table->string('reason', 64)->nullable();
            $table->string('reason_item', 120)->nullable();      // z. B. „Wohnungswechsel“
            $table->boolean('with_attest')->nullable();          // true/false bei Krankheit mit Attest
            $table->integer('fee_cents')->nullable();            // 2000 = 20,00 €

            // Externe Prüfungen
            $table->string('exam_modality', 32)->nullable();     // "online" | "praesenz"
            $table->string('certification_key', 64)->nullable(); // ID/Key der Zertifizierung
            $table->string('certification_label', 180)->nullable(); // Anzeigename

            // Freitext-Felder aus Formularen
            $table->string('class_label', 64)->nullable();       // Freitext "Klasse"
            $table->string('email_priv', 190)->nullable();       // private E-Mail (optional)

            // Datei-Anhang (z. B. Attest, Nachweise) – Pfad auf 'public' Disk
            $table->string('attachment_path')->nullable();

            // Status & Workflow
            $table->enum('status', ['pending','approved','rejected','canceled'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('admin_comment')->nullable();

            // Flexible Zusatzdaten (Rohwerte/Hidden aus Altformularen, send_date, usw.)
            $table->json('data')->nullable();

            // Timestamps
            $table->timestamps();

            // Indizes
            $table->index(['user_id', 'type', 'status']);
            $table->index('scheduled_at');
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_requests');
    }
};
