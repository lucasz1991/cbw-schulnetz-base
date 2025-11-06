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
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete()
                ->comment('Antragsteller (User)');

            // Klassifizierung / Basis
            // makeup (interne Nachprüfung), external_makeup (externe), absence (Fehlzeit), general (sonstiges)
            $table->string('type', 40)
                ->default('general')
                ->comment('Antragstyp: absence|makeup|external_makeup|general|...');

            // Personen-/Klassendaten (optional)
            $table->string('class_code', 32)->nullable()->comment('Klassen-/Kurskürzel, z. B. INF23A');
            $table->string('institute', 64)->nullable()->comment('Standort, z. B. Köln');
            $table->string('participant_no', 32)->nullable()->comment('Teilnehmernummer/UVS');

            // Titel / Freitext
            $table->string('title', 200)->nullable()->comment('Kurzer Betreff/Titel (optional)');
            $table->text('message')->nullable()->comment('Freitext');

            // Zeiträume / Termine
            $table->date('date_from')->nullable()->comment('Startdatum oder Einzeldatum (z. B. Fehltag)');
            $table->date('date_to')->nullable()->comment('Ende des Zeitraums (optional)');
            $table->date('original_exam_date')->nullable()->comment('Ursprüngliche Prüfung (falls relevant)');
            $table->dateTimeTz('scheduled_at')->nullable()->comment('Geplanter Termin (z. B. Nachprüfung)');

            // Prüfungsbezogene Felder
            $table->string('module_code', 32)->nullable()->comment('Baustein/Modulcode');
            $table->string('instructor_name', 120)->nullable()->comment('Dozent/Instruktor');

            // Abwesenheitsspezifisch
            $table->boolean('full_day')->default(false)->comment('Ganztägig gefehlt (Absence)');
            $table->time('time_arrived_late')->nullable()->comment('Später gekommen (HH:MM)');
            $table->time('time_left_early')->nullable()->comment('Früher gegangen (HH:MM)');

            // Gründe / Gebühren
            // reason z. B.: unter51 | krankMitAtest | krankOhneAtest | abw_wichtig | abw_unwichtig | zert_faild
            $table->string('reason', 64)->nullable()->comment('Grund-Schlüssel z. B. krankMitAtest');
            $table->string('reason_item', 120)->nullable()->comment('Feinere Auswahl, z. B. „Wohnungswechsel“');
            $table->boolean('with_attest')->nullable()->comment('Attest liegt vor (Krankheit)');

            // 2000 => 20,00 € (immer in Cent speichern)
            $table->unsignedInteger('fee_cents')->nullable()->comment('Gebühr in Cent');

            // Externe Prüfungen
            $table->string('exam_modality', 32)->nullable()->comment('online|praesenz (externe Prüfungen)');
            $table->string('certification_key', 64)->nullable()->comment('Zertifizierungs-ID/Key');
            $table->string('certification_label', 180)->nullable()->comment('Zertifizierungsname (anzeige)');

            // Freitext-Felder aus Formularen
            $table->string('class_label', 64)->nullable()->comment('Freitext „Klasse“ aus Formular');
            $table->string('email_priv', 190)->nullable()->comment('Private E-Mail (optional)');

            // Datei-Anhang (Legacy-Einzeldatei; empfohlen: zusätzlich morphMany Files nutzen)
            $table->string('attachment_path')->nullable()->comment('Pfad zur Einzeldatei (Legacy)');

            // Status & Workflow (string statt ENUM -> flexibler)
            // Empfohlen: submitted|in_review|approved|rejected|canceled
            $table->string('status', 24)
                ->default('pending')
                ->comment('Workflow-Status: pending|approved|rejected|canceled|...');

            $table->dateTimeTz('submitted_at')->nullable()->comment('Zeitpunkt der Einreichung');
            $table->dateTimeTz('decided_at')->nullable()->comment('Zeitpunkt der Entscheidung');
            $table->text('admin_comment')->nullable()->comment('Interner Kommentar/Begründung');

            // Flexible Zusatzdaten (z. B. Raw-Werte aus Altformularen, send_date etc.)
            $table->json('data')->nullable()->comment('Typspezifische Zusatzdaten (JSON)');

            // Timestamps 
            $table->timestamps();

            // Indizes
            $table->index(['user_id', 'type', 'status'], 'ur_user_type_status_idx');
            $table->index('scheduled_at', 'ur_scheduled_at_idx');
            $table->index(['date_from', 'date_to'], 'ur_date_range_idx');
            $table->index('class_code', 'ur_class_code_idx');
            $table->index('participant_no', 'ur_participant_no_idx');
            $table->index('institute', 'ur_institute_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_requests');
    }
};
