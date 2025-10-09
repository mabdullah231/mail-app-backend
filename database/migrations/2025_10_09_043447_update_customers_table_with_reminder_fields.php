<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add reminder_start_date if it doesn't exist
            if (!Schema::hasColumn('customers', 'reminder_start_date')) {
                $table->date('reminder_start_date')->nullable()->after('frequency');
            }
            
            // Add notification_rules if it doesn't exist
            if (!Schema::hasColumn('customers', 'notification_rules')) {
                $table->json('notification_rules')->nullable()->after('reminder_start_date');
            }
            
            // Add unsubscribe_option if it doesn't exist
            if (!Schema::hasColumn('customers', 'unsubscribe_option')) {
                $table->boolean('unsubscribe_option')->default(true)->after('notification_rules');
            }
            
            // Add template_id if it doesn't exist (this might be in a separate migration)
            if (!Schema::hasColumn('customers', 'template_id')) {
                $table->foreignId('template_id')
                      ->nullable()
                      ->constrained('templates')
                      ->onDelete('set null')
                      ->after('notification');
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_start_date',
                'notification_rules', 
                'unsubscribe_option'
            ]);
            
            // Don't drop template_id here as it's handled by its own migration
        });
    }
};