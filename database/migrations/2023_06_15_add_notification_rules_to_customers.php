<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotificationRulesToCustomers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('notification_rules')->nullable()->after('reminder_start_date');
            // Drop the old columns that are being replaced
            $table->dropColumn(['reminder_days_before', 'reminder_days_after']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('notification_rules');
            $table->integer('reminder_days_before')->nullable()->after('reminder_start_date');
            $table->integer('reminder_days_after')->nullable()->after('reminder_days_before');
        });
    }
}