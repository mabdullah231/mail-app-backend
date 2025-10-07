<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->enum('plan_type', ['free', 'monthly', '3_months', '6_months', '12_months'])->default('free');
            $table->decimal('amount', 8, 2)->default(0.00);
            $table->dateTime('starts_at');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('remove_branding')->default(false);
            $table->json('limits')->nullable(); // {"emails_per_month": 1000, "templates": 10, "sms_per_month": 100}
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->string('payment_id')->nullable(); // PayPal/Stripe transaction ID
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('company_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
