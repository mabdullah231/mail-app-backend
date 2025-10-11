<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company_details', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('company_details', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('company_details', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            
            if (!Schema::hasColumn('company_details', 'country')) {
                $table->string('country')->nullable()->after('phone');
            }
            
            if (!Schema::hasColumn('company_details', 'business_email')) {
                $table->string('business_email')->nullable()->after('address');
            }
            
            if (!Schema::hasColumn('company_details', 'business_email_password')) {
                $table->string('business_email_password')->nullable()->after('business_email');
            }
            
            if (!Schema::hasColumn('company_details', 'smtp_host')) {
                $table->string('smtp_host')->nullable()->after('business_email_password');
            }
            
            if (!Schema::hasColumn('company_details', 'smtp_port')) {
                $table->string('smtp_port')->nullable()->after('smtp_host');
            }
            
            if (!Schema::hasColumn('company_details', 'smtp_encryption')) {
                $table->string('smtp_encryption')->nullable()->after('smtp_port');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_details', function (Blueprint $table) {
            // Only drop columns if they exist
            if (Schema::hasColumn('company_details', 'email')) {
                $table->dropColumn('email');
            }
            
            if (Schema::hasColumn('company_details', 'phone')) {
                $table->dropColumn('phone');
            }
            
            if (Schema::hasColumn('company_details', 'country')) {
                $table->dropColumn('country');
            }
            
            if (Schema::hasColumn('company_details', 'business_email')) {
                $table->dropColumn('business_email');
            }
            
            if (Schema::hasColumn('company_details', 'business_email_password')) {
                $table->dropColumn('business_email_password');
            }
            
            if (Schema::hasColumn('company_details', 'smtp_host')) {
                $table->dropColumn('smtp_host');
            }
            
            if (Schema::hasColumn('company_details', 'smtp_port')) {
                $table->dropColumn('smtp_port');
            }
            
            if (Schema::hasColumn('company_details', 'smtp_encryption')) {
                $table->dropColumn('smtp_encryption');
            }
        });
    }
};