<?php

namespace App\Domain\Product\Services;

use App\Domain\Inventory\Models\InventoryStock;
use App\Domain\Inventory\Services\InventoryWriter;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Product\Models\Sport;
use App\Domain\Product\Support\CatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductWriter
{
    public function __construct(private InventoryWriter $inventory) {}
    public function createSport(array $data): Sport
    {
        $sport = Sport::query()->create($this->withSlug($data, Sport::class));
        CatalogCache::bump();

        return $sport;
    }

    public function updateSport(Sport $sport, array $data): Sport
    {
        $sport->update($this->withSlug($data, Sport::class, $sport->id));
        CatalogCache::bump();

        return $sport->refresh();
    }

    public function deleteSport(Sport $sport): void
    {
        $sport->delete();
        CatalogCache::bump();
    }

    public function createCategory(array $data): Category
    {
        $category = Category::query()->create($this->withSlug($data, Category::class));
        CatalogCache::bump();

        return $category;
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($this->withSlug($data, Category::class, $category->id));
        CatalogCache::bump();

        return $category->refresh();
    }

    public function deleteCategory(Category $category): void
    {
        $category->delete();
        CatalogCache::bump();
    }

    public function createProduct(array $data): Product
    {
        $product = Product::query()->create($this->withSlug($data, Product::class));
        CatalogCache::bump();

        return $product->load(['category', 'sport', 'variants']);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($this->withSlug($data, Product::class, $product->id));
        CatalogCache::bump();

        return $product->refresh()->load(['category', 'sport', 'variants']);
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
        CatalogCache::bump();
    }

    public function createVariant(Product $product, array $data): ProductVariant
    {
        $variant = $product->variants()->create($data);
        CatalogCache::bump();

        return $variant->refresh();
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);
        CatalogCache::bump();

        return $variant->refresh();
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        $variant->delete();
        CatalogCache::bump();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveWithVariants(array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($data, $product) {
            $attrs = $this->withSlug([
                'category_id' => $data['category_id'],
                'sport_id' => $data['sport_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'offer_mode' => $data['offer_mode'],
                'is_active' => $data['is_active'] ?? true,
            ], Product::class, $product?->id);

            if (! empty($data['image_url'])) {
                $attrs['image_url'] = $data['image_url'];
            }

            if ($product) {
                $product->update($attrs);
            } else {
                $product = Product::query()->create($attrs);
            }

            $kept = [];
            foreach ($data['variants'] as $row) {
                $payload = [
                    'sku' => trim($row['sku']),
                    'size' => $row['size'] ?? null,
                    'color' => $row['color'] ?? null,
                    'sale_price' => $row['sale_price'] !== '' && $row['sale_price'] !== null ? $row['sale_price'] : null,
                    'rental_price_per_day' => $row['rental_price_per_day'] !== '' && $row['rental_price_per_day'] !== null ? $row['rental_price_per_day'] : null,
                    'deposit_amount' => $row['deposit_amount'] !== '' && $row['deposit_amount'] !== null ? $row['deposit_amount'] : null,
                    'is_active' => true,
                ];

                if (! empty($row['id'])) {
                    $variant = $product->variants()->whereKey($row['id'])->firstOrFail();
                    $variant->update($payload);
                } else {
                    $variant = $product->variants()->create($payload);
                }

                $kept[] = $variant->id;
                InventoryStock::query()->updateOrCreate(
                    ['product_variant_id' => $variant->id],
                    [
                        'quantity_on_hand' => (int) ($row['quantity'] ?? 0),
                        'low_stock_threshold' => 5,
                    ],
                );

                $mode = $data['offer_mode'] ?? '';
                if ($mode === 'rental' && $payload['rental_price_per_day'] !== null) {
                    $this->inventory->syncRentalPool($variant->id, (int) ($row['quantity'] ?? 0));
                }
                if ($mode === 'both' && $payload['rental_price_per_day'] !== null) {
                    $this->inventory->syncRentalPool($variant->id, (int) ($row['rental_quantity'] ?? $variant->items()->count()));
                }
            }

            $product->variants()->whereNotIn('id', $kept)->get()->each(function (ProductVariant $variant) {
                $variant->stock?->delete();
                $variant->delete();
            });

            CatalogCache::bump();

            return $product->refresh()->load(['category', 'sport', 'variants.stock']);
        });
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function withSlug(array $data, string $model, ?int $ignoreId = null): array
    {
        if (! isset($data['name'])) {
            return $data;
        }

        $base = Str::slug((string) $data['name']) ?: 'item';
        $slug = $base;
        $i = 2;

        while ($model::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        $data['slug'] = $slug;

        return $data;
    }
}
