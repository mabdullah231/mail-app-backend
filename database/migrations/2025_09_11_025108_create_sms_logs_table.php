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
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('response')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('company_details')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
