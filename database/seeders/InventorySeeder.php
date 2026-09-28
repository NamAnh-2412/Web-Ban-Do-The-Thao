<?php

namespace Database\Seeders;

use App\Domain\Inventory\Enums\ItemStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            'GIAY-COPA-42' => ['on_hand' => 12, 'rent_items' => 2],
            'GIAY-COPA-43' => ['on_hand' => 8, 'rent_items' => 1],
            'VOT-WIL-STD' => ['on_hand' => null, 'rent_items' => 3],
            'AO-GYM-M' => ['on_hand' => 30, 'rent_items' => 0],
            'AO-GYM-L' => ['on_hand' => 18, 'rent_items' => 0],
        ];

        foreach ($rows as $sku => $cfg) {
            $variant = ProductVariant::query()->where('sku', $sku)->first();
            if ($variant === null) {
                continue;
            }

            if ($cfg['on_hand'] !== null) {
                InventoryStock::query()->firstOrCreate(
                    ['product_variant_id' => $variant->id],
                    [
                        'quantity_on_hand' => $cfg['on_hand'],
                        'low_stock_threshold' => 5,
                    ],
                );
            }

            for ($i = 1; $i <= $cfg['rent_items']; $i++) {
                InventoryItem::query()->firstOrCreate(
                    ['asset_code' => $sku.'-'.$i],
                    [
                        'product_variant_id' => $variant->id,
                        'status' => ItemStatus::Available,
                        'condition_note' => 'Món thuê mẫu',
                    ],
                );
            }
        }
    }
}
