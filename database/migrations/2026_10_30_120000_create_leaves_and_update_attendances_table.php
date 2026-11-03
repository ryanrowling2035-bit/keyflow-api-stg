<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create leaves table if not exists, or add missing columns
        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('staff_name')->nullable();
                $table->string('leave_type'); // annual, sick, emergency, unpaid, wfh, compensatory
                $table->date('start_date');
                $table->date('end_date');
                $table->date('last_working_day')->nullable();
                $table->date('return_date')->nullable();
                $table->decimal('working_days', 5, 2)->default(1.00);
                $table->text('reason');
                $table->string('has_manager_approved')->default('Yes');
                $table->string('handover_to')->nullable();
                $table->string('has_doctor_note')->default('NO');
                $table->string('attachment_url')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
                $table->text('rejection_reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'status']);
                $table->index(['start_date', 'end_date']);
            });
        } else {
            Schema::table('leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('leaves', 'staff_name')) {
                    $table->string('staff_name')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('leaves', 'last_working_day')) {
                    $table->date('last_working_day')->nullable()->after('end_date');
                }
                if (!Schema::hasColumn('leaves', 'return_date')) {
                    $table->date('return_date')->nullable()->after('last_working_day');
                }
                if (!Schema::hasColumn('leaves', 'has_manager_approved')) {
                    $table->string('has_manager_approved')->default('Yes')->after('reason');
                }
                if (!Schema::hasColumn('leaves', 'handover_to')) {
                    $table->string('handover_to')->nullable()->after('has_manager_approved');
                }
                if (!Schema::hasColumn('leaves', 'has_doctor_note')) {
                    $table->string('has_doctor_note')->default('NO')->after('handover_to');
                }
            });
        }

        // 2. Create leave_balances table if not exists
        if (!Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->integer('year')->default(2026);

                // Quotas
                $table->decimal('annual_quota', 5, 2)->default(21.00);
                $table->decimal('sick_quota', 5, 2)->default(10.00);
                $table->decimal('emergency_quota', 5, 2)->default(5.00);
                $table->decimal('unpaid_quota', 5, 2)->default(30.00);
                $table->decimal('wfh_quota', 5, 2)->default(12.00);
                $table->decimal('compensatory_quota', 5, 2)->default(5.00);

                // Used
                $table->decimal('annual_used', 5, 2)->default(0.00);
                $table->decimal('sick_used', 5, 2)->default(0.00);
                $table->decimal('emergency_used', 5, 2)->default(0.00);
                $table->decimal('unpaid_used', 5, 2)->default(0.00);
                $table->decimal('wfh_used', 5, 2)->default(0.00);
                $table->decimal('compensatory_used', 5, 2)->default(0.00);

                $table->timestamps();

                $table->unique(['user_id', 'year']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leaves');
    }
};

