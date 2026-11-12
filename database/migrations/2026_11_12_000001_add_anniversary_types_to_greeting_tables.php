<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify type column in greeting_templates to support Work Anniversary, 5 Year Milestone, etc.
        if (Schema::hasTable('greeting_templates')) {
            DB::statement("ALTER TABLE `greeting_templates` MODIFY COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'Birthday'");
        }

        // Modify type column in greeting_logs to support Work Anniversary, 5 Year Milestone, etc.
        if (Schema::hasTable('greeting_logs')) {
            DB::statement("ALTER TABLE `greeting_logs` MODIFY COLUMN `type` VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('greeting_templates')) {
            DB::statement("ALTER TABLE `greeting_templates` MODIFY COLUMN `type` ENUM('Birthday', 'Holiday') NOT NULL DEFAULT 'Birthday'");
        }

        if (Schema::hasTable('greeting_logs')) {
            DB::statement("ALTER TABLE `greeting_logs` MODIFY COLUMN `type` ENUM('Birthday', 'Holiday') NOT NULL");
        }
    }
};

