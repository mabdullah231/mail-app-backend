<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id'); // FK to company_details
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();

            $table->boolean('sms_opt_in')->default(false);
            $table->enum('notification', ['email', 'sms', 'both'])->default('email');

            $table->string('template')->nullable(); // E.g., "Subscription Reminder"
            $table->enum('frequency', ['Daily', '3 days', 'Weekly', '2 weeks'])->default('Weekly');

            $table->timestamps(); // includes created_at (used as startDate)

            $table->foreign('company_id')->references('id')->on('company_details')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
