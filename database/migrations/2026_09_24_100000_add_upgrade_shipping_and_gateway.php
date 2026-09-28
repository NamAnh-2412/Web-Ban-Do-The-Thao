<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_method', 20)->default('pickup')->after('channel');
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('discount_total');
            $table->unsignedBigInteger('to_province_id')->nullable()->after('shipping_address');
            $table->unsignedBigInteger('to_district_id')->nullable()->after('to_province_id');
            $table->string('to_ward_code', 50)->nullable()->after('to_district_id');
            $table->string('ghn_order_code')->nullable()->after('to_ward_code');
            $table->string('fulfillment_status', 30)->nullable()->after('ghn_order_code');
        });

        Schema::create('gateway_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('provider_txn_id')->nullable();
            $table->integer('result_code')->nullable();
            $table->string('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'gateway', 'status']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'bank_transfer', 'vnpay', 'momo', 'cod') NOT NULL DEFAULT 'cash'");
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('method', 32)->default('cash')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_sessions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_method',
                'shipping_fee',
                'to_province_id',
                'to_district_id',
                'to_ward_code',
                'ghn_order_code',
                'fulfillment_status',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY method ENUM('cash', 'bank_transfer', 'vnpay', 'momo') NOT NULL DEFAULT 'cash'");
        }
    }
};
