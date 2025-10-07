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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('title');
            $table->longText('body_html');   // WYSIWYG HTML
            $table->json('placeholders')->nullable(); // e.g. ["{{customer.name}}","{{business.logo}}"]
            $table->json('attachments')->nullable();  // image/file paths
            $table->enum('type', ['email', 'sms'])->default('email');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('company_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
