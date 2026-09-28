<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM('simulation', 'cash', 'bank_transfer', 'vnpay', 'momo') NOT NULL DEFAULT 'cash'");
        DB::table('payments')->where('method', 'simulation')->update(['method' => 'cash']);
        DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'bank_transfer', 'vnpay', 'momo') NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments MODIFY method ENUM('simulation', 'cash', 'bank_transfer', 'vnpay', 'momo') NOT NULL DEFAULT 'simulation'");
        DB::table('payments')->where('method', 'cash')->update(['method' => 'simulation']);
        DB::statement("ALTER TABLE payments MODIFY method ENUM('simulation', 'bank_transfer', 'vnpay', 'momo') NOT NULL DEFAULT 'simulation'");
    }
};
