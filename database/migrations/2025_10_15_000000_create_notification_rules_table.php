<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('event_type'); // e.g. 'subscription_expiry', 'birthday', etc.
            $table->enum('timing', ['before', 'on', 'after']);
            $table->integer('days_offset')->nullable(); // e.g. -7 for 1 week before, 2 for 2 days after
            $table->date('custom_date')->nullable(); // for custom notification dates
            $table->enum('channel', ['email', 'sms', 'both'])->default('email');
            $table->boolean('recurring')->default(false);
            $table->string('recurrence_interval')->nullable(); // e.g. 'weekly', 'biweekly'
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notification_rules');
    }
};
