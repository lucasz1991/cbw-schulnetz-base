<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id(); // id als PK

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('person_id')->unique();
            $table->string('institut_id');
            $table->string('person_nr');
            $table->string('status')->nullable();
            $table->dateTime('upd_date')->nullable();
            $table->string('nachname');
            $table->string('vorname');
            $table->string('geschlecht', 10)->nullable();
            $table->string('titel_kennz')->nullable();
            $table->string('nationalitaet')->nullable();
            $table->string('familien_stand')->nullable();
            $table->date('geburt_datum')->nullable();
            $table->string('geburt_name')->nullable();
            $table->string('geburt_land')->nullable();
            $table->string('geburt_ort')->nullable();
            $table->string('lkz')->nullable();
            $table->string('plz')->nullable();
            $table->string('ort')->nullable();
            $table->string('strasse')->nullable();
            $table->string('adresszusatz1')->nullable();
            $table->string('adresszusatz2')->nullable();
            $table->string('plz_pf')->nullable();
            $table->string('postfach')->nullable();
            $table->string('plz_gk')->nullable();
            $table->string('telefon1')->nullable();
            $table->string('telefon2')->nullable();
            $table->string('person_kz')->nullable();
            $table->string('plz_alt')->nullable();
            $table->string('ort_alt')->nullable();
            $table->string('strasse_alt')->nullable();
            $table->string('telefax')->nullable();
            $table->string('kunden_nr')->nullable();
            $table->string('stamm_nr_aa')->nullable();
            $table->string('stamm_nr_bfd')->nullable();
            $table->string('stamm_nr_sons')->nullable();
            $table->string('stamm_nr_kst')->nullable();
            $table->string('kostentraeger')->nullable();
            $table->string('bkz')->nullable();
            $table->string('email_priv')->nullable();
            $table->string('email_cbw')->nullable();
            $table->string('geb_mmtt')->nullable();
            $table->string('org_zeichen')->nullable();
            $table->string('personal_nr')->nullable();
            $table->string('kred_nr')->nullable();
            $table->dateTime('angestellt_von')->nullable();
            $table->dateTime('angestellt_bis')->nullable();
            $table->string('leer')->nullable();
            $table->json('programdata')->nullable();
            $table->json('statusdata')->nullable();
            $table->timestamp('last_api_update')->nullable();
            $table->timestamps(); // created_at & updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
