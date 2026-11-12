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
        if (!Schema::hasTable('contact_emails')) {
            Schema::create('contact_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('message_id')->unique();
                $table->string('conversation_id')->nullable()->index();
                $table->string('sender_name')->nullable();
                $table->string('sender_email');
                $table->string('subject')->nullable();
                $table->text('snippet')->nullable();
                $table->longText('body')->nullable();
                $table->boolean('is_incoming')->default(true);
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_emails');
    }
};

