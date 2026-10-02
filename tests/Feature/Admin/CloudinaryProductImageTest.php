<?php

namespace Tests\Feature\Admin;

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\User\Models\User;
use App\Gateway\Media\CloudinaryImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudinaryProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_upload_stores_the_cloudinary_url(): void
    {
        config([
            'services.cloudinary.cloud_name' => 'demo',
            'services.cloudinary.api_key' => 'key',
            'services.cloudinary.api_secret' => 'secret',
        ]);
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/webthethao/products/ao.jpg',
            ]),
        ]);

        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Áo Cloudinary',
            'category_id' => $category->id,
            'offer_mode' => 'sale',
            'image' => UploadedFile::fake()->createWithContent('ao.jpg', base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
            )),
            'variants' => [[
                'sku' => 'CLD-AO-1',
                'size' => 'M',
                'color' => 'Đen',
                'sale_price' => 100000,
                'rental_price_per_day' => '',
                'deposit_amount' => '',
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Áo Cloudinary')->first();
        $this->assertNotNull($product);
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/v1/webthethao/products/ao.jpg',
            $product->image_url,
        );
        Http::assertSent(fn ($request) => str_contains($request->url(), '/image/upload'));
    }

    public function test_public_id_is_read_from_a_cloudinary_url(): void
    {
        $store = new CloudinaryImageStore;

        $this->assertSame(
            'webthethao/products/ao',
            $store->publicId('https://res.cloudinary.com/demo/image/upload/v172000/webthethao/products/ao.jpg'),
        );
        $this->assertNull($store->publicId('products/local.jpg'));
    }
}
