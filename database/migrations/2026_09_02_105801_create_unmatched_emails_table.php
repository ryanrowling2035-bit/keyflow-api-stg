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
        if (!Schema::hasTable('unmatched_emails')) {
            Schema::create('unmatched_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('messageId')->nullable()->index();
                $table->string('senderName')->nullable();
                $table->string('senderEmail');
                $table->string('subject')->nullable();
                $table->text('snippet')->nullable();
                $table->longText('body')->nullable();
                $table->timestamp('dateReceived')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unmatched_emails');
    }
};

