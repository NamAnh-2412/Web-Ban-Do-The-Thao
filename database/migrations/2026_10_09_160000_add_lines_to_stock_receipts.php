<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sale_quantity')->default(0);
            $table->unsignedInteger('rental_quantity')->default(0);
            $table->timestamps();
        });

        if (Schema::hasColumn('stock_receipts', 'product_variant_id')) {
            foreach (DB::table('stock_receipts')->orderBy('id')->get() as $receipt) {
                DB::table('stock_receipt_lines')->insert([
                    'stock_receipt_id' => $receipt->id,
                    'product_variant_id' => $receipt->product_variant_id,
                    'sale_quantity' => $receipt->sale_quantity,
                    'rental_quantity' => $receipt->rental_quantity,
                    'created_at' => $receipt->created_at,
                    'updated_at' => $receipt->updated_at,
                ]);
            }

            Schema::table('stock_receipts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_variant_id');
                $table->dropColumn(['sale_quantity', 'rental_quantity']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedInteger('sale_quantity')->default(0)->after('product_variant_id');
            $table->unsignedInteger('rental_quantity')->default(0)->after('sale_quantity');
        });

        foreach (DB::table('stock_receipt_lines')->orderBy('id')->get() as $line) {
            DB::table('stock_receipts')->where('id', $line->stock_receipt_id)->update([
                'product_variant_id' => $line->product_variant_id,
                'sale_quantity' => $line->sale_quantity,
                'rental_quantity' => $line->rental_quantity,
            ]);
        }

        Schema::dropIfExists('stock_receipt_lines');
    }
};
