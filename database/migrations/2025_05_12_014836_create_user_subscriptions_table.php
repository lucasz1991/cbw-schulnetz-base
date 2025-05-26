<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('payment_method')->nullable(); // z. B. 'stripe', 'paypal', 'manual'
            $table->string('interval')->default('monthly'); // z. B. 'monthly' oder 'yearly'
            $table->json('payment_data')->nullable(); // z. B. Stripe-Infos, Rechnungen, Status

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
