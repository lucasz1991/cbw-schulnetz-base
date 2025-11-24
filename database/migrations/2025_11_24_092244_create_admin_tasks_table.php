<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();

            // Wer hat die Aufgabe erstellt?
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Generischer Kontext: z.B. User, Course, UserRequest, ...
            $table->nullableMorphs('context'); // context_type, context_id (beides nullable)

            // Aufgabenart (z.B. "User kontaktieren", "Dokument prüfen", ...)
            $table->string('task_type')->index();

            // Beschreibung
            $table->text('description')->nullable();

            // Status: 0 = offen, 1 = in Bearbeitung, 2 = erledigt
            $table->unsignedTinyInteger('status')
                ->default(0)
                ->index();

            // Priorität: 1 = hoch, 2 = normal, 3 = niedrig
            $table->unsignedTinyInteger('priority')
                ->default(2)
                ->index();

            // Fälligkeitsdatum (optional)
            $table->timestamp('due_at')->nullable()->index();

            // Zugewiesener Admin (Bearbeiter)
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Zeitpunkt des Abschlusses
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_tasks');
    }
};
