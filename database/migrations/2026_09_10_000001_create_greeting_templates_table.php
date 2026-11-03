<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greeting_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['Birthday', 'Holiday'])->default('Birthday');
            $table->string('trigger_event')->default('On Birthday');
            $table->date('holiday_date')->nullable();
            $table->string('send_time', 5)->default('09:00');
            $table->enum('status', ['Active', 'Disabled'])->default('Active');
            $table->string('target_audience')->default('Clients');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greeting_templates');
    }
};
