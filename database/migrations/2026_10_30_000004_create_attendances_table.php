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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_name')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->text('profile_photo')->nullable();
            $table->date('date');
            $table->string('status', 50)->default('present'); // present, late, absent, half_day, on_leave, holiday, weekend
            $table->string('check_in')->nullable();
            $table->string('check_out')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('work_location')->default('Office');
            $table->string('working_hours')->nullable();
            $table->text('notes')->nullable();
            $table->string('remarks')->nullable();
            $table->string('updated_by')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

