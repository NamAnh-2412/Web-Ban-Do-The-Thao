<?php

namespace Database\Seeders;

use App\Domain\Product\Enums\OfferMode;
use App\Domain\Product\Enums\VariantCondition;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sports = collect(['Bóng đá', 'Tennis', 'Gym'])->map(function (string $name, int $i) {
            return Sport::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1, 'is_active' => true],
            );
        });

        $categories = collect(['Dụng cụ', 'Quần áo', 'Giày'])->map(function (string $name) {
            return Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'parent_id' => null, 'is_active' => true],
            );
        });

        $items = [
            [
                'name' => 'Giày bóng đá Copa',
                'offer_mode' => OfferMode::Both,
                'sport' => 'Bóng đá',
                'category' => 'Giày',
                'variants' => [
                    ['sku' => 'GIAY-COPA-42', 'size' => '42', 'color' => 'Đen', 'sale' => 890000, 'rent' => 50000, 'deposit' => 200000],
                    ['sku' => 'GIAY-COPA-43', 'size' => '43', 'color' => 'Đen', 'sale' => 890000, 'rent' => 50000, 'deposit' => 200000],
                ],
            ],
            [
                'name' => 'Vợt tennis Wilson',
                'offer_mode' => OfferMode::Rental,
                'sport' => 'Tennis',
                'category' => 'Dụng cụ',
                'variants' => [
                    ['sku' => 'VOT-WIL-STD', 'size' => null, 'color' => 'Đỏ', 'sale' => null, 'rent' => 80000, 'deposit' => 300000],
                ],
            ],
            [
                'name' => 'Áo gym nam',
                'offer_mode' => OfferMode::Sale,
                'sport' => 'Gym',
                'category' => 'Quần áo',
                'variants' => [
                    ['sku' => 'AO-GYM-M', 'size' => 'M', 'color' => 'Xám', 'sale' => 219000, 'rent' => null, 'deposit' => null],
                    ['sku' => 'AO-GYM-L', 'size' => 'L', 'color' => 'Xám', 'sale' => 219000, 'rent' => null, 'deposit' => null],
                ],
            ],
        ];

        foreach ($items as $item) {
            $product = Product::query()->firstOrCreate(
                ['slug' => Str::slug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categories->firstWhere('name', $item['category'])->id,
                    'sport_id' => $sports->firstWhere('name', $item['sport'])->id,
                    'description' => $item['name'].' — dữ liệu mẫu WebTheThao.',
                    'offer_mode' => $item['offer_mode'],
                    'image_url' => '/media/images/'.Str::slug($item['name']).'.jpg',
                    'is_active' => true,
                ],
            );

            foreach ($item['variants'] as $row) {
                $product->variants()->firstOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'size' => $row['size'],
                        'color' => $row['color'],
                        'condition' => VariantCondition::New,
                        'sale_price' => $row['sale'],
                        'rental_price_per_day' => $row['rent'],
                        'rental_price_per_week' => $row['rent'] ? $row['rent'] * 6 : null,
                        'deposit_amount' => $row['deposit'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
