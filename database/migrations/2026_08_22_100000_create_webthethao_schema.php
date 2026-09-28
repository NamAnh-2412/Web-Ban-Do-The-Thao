<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['customer', 'staff', 'admin'])->default('customer');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50)->default('Nhà');
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('address_line');
            $table->string('ward')->nullable();
            $table->string('district')->nullable();
            $table->string('city');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained('sports')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('offer_mode', ['sale', 'rental', 'both']);
            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sport_id', 'category_id'], 'products_filter_idx');
            $table->index(['offer_mode', 'is_active']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('size', 30)->nullable();
            $table->string('color', 50)->nullable();
            $table->enum('condition', ['new', 'used'])->default('new');
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->decimal('rental_price_per_day', 12, 2)->nullable();
            $table->decimal('rental_price_per_week', 12, 2)->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'size'], 'variants_active_size_idx');
            $table->index(['is_active', 'color'], 'variants_active_color_idx');
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('asset_code')->unique();
            $table->enum('status', ['available', 'rented', 'inspecting', 'maintenance'])->default('available');
            $table->text('condition_note')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('channel', ['online', 'pos'])->default('online');
            $table->enum('status', ['pending', 'confirmed', 'paid', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->decimal('merchandise_total', 12, 2)->default(0);
            $table->decimal('rental_total', 12, 2)->default(0);
            $table->decimal('deposit_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone', 20)->nullable();
            $table->string('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['channel', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('line_type', ['sale', 'rental']);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('product_name');
            $table->string('sku');
            $table->string('image_url')->nullable();
            $table->string('size', 30)->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->date('rental_start')->nullable();
            $table->date('rental_end')->nullable();
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('line_type');
        });

        Schema::create('inventory_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_id')->constrained('inventory_stocks')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['pending', 'committed', 'released'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_item_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'committed', 'released'])->default('pending');
            $table->timestamps();
            $table->index(['inventory_item_id', 'start_date', 'end_date'], 'item_res_dates_idx');
        });

        Schema::create('rental_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('daily_rate', 12, 2);
            $table->decimal('rental_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'active', 'returned', 'overdue', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_booking_id')->constrained('rental_bookings')->cascadeOnDelete();
            $table->date('old_end_date');
            $table->date('new_end_date');
            $table->decimal('extra_amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });

        Schema::create('rental_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_booking_id')->unique()->constrained('rental_bookings')->cascadeOnDelete();
            $table->timestamp('returned_at');
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('condition', ['good', 'damaged', 'lost'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_booking_id')->constrained('rental_bookings')->cascadeOnDelete();
            $table->foreignId('rental_return_id')->nullable()->constrained('rental_returns')->nullOnDelete();
            $table->enum('type', ['damage', 'late', 'lost']);
            $table->text('description')->nullable();
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('kind', ['merchandise', 'deposit', 'refund']);
            $table->enum('method', ['cash', 'bank_transfer', 'vnpay', 'momo'])->default('cash');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('provider_txn_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['kind', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('type', ['order_confirmed', 'rental_due_reminder', 'rental_overdue']);
            $table->enum('channel', ['email'])->default('email');
            $table->string('subject');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('kind', ['sale', 'rental']);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'order_item_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->enum('applies_to', ['sale', 'rental', 'both'])->default('both');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 12, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['image', 'video']);
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('owner_type', 50)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('rental_incidents');
        Schema::dropIfExists('rental_returns');
        Schema::dropIfExists('rental_extensions');
        Schema::dropIfExists('rental_bookings');
        Schema::dropIfExists('inventory_item_reservations');
        Schema::dropIfExists('inventory_stock_reservations');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sports');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_addresses');
        Schema::dropIfExists('users');
    }
};
