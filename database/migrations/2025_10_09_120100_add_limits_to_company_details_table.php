<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_details', function (Blueprint $table) {
            $table->integer('email_limit')->nullable()->after('address');
            $table->integer('template_limit')->nullable()->after('email_limit');
            $table->integer('sms_limit')->nullable()->after('template_limit');
            $table->integer('storage_limit')->nullable()->after('sms_limit'); // in MB
            $table->json('features_disabled')->nullable()->after('storage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('company_details', function (Blueprint $table) {
            $table->dropColumn(['email_limit', 'template_limit', 'sms_limit', 'storage_limit', 'features_disabled']);
        });
    }
};


