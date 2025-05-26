<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 8, 2)->nullable();
            $table->decimal('price_yearly', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable(); // 👈 hier
            $table->timestamps();
        });
        DB::table('subscription_plans')->insert([
            
                [
                    'name' => 'Basic',
                    'slug' => 'basic',
                    'price_monthly' => 29.00,
                    'price_yearly' => 290.00,
                    'is_active' => true,
                    'permissions' => json_encode([
                        'Eigenes Profil auf der Plattform',
                        'Badge „Zertifizierter Partner“',
                        'Öffentliches Partnerverzeichnis',
                    ]),
                ],
                [
                    'name' => 'Premium',
                    'slug' => 'premium',
                    'price_monthly' => 59.00,
                    'price_yearly' => 590.00,
                    'is_active' => true,
                    'permissions' => json_encode([
                        'Alle Leistungen aus Basic',
                        'Priorisierte Darstellung',
                        'Lead-Weiterleitung',
                    ]),
                ],
                [
                    'name' => 'Exklusiv',
                    'slug' => 'exklusiv',
                    'price_monthly' => 129.00,
                    'price_yearly' => 1290.00,
                    'is_active' => true,
                    'permissions' => json_encode([
                        'Alle Leistungen aus Premium',
                        'Exklusive Region',
                        'Prominente Startseitendarstellung',
                        'Externe Medienwerbung',
                    ]),
                ],
            
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
