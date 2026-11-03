<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greeting_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('greeting_templates')->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->enum('type', ['Birthday', 'Holiday']);
            $table->string('contact_id')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->enum('status', ['Sent', 'Failed', 'Pending'])->default('Pending');
            $table->text('compiled_subject')->nullable();
            $table->longText('compiled_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['template_id', 'contact_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greeting_logs');
    }
};
