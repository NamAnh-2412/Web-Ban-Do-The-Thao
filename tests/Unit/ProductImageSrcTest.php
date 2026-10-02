<?php

namespace Tests\Unit;

use App\Domain\Product\Models\Product;
use Tests\TestCase;

class ProductImageSrcTest extends TestCase
{
    public function test_seed_media_path_uses_the_public_file_when_it_exists(): void
    {
        $product = new Product(['image_url' => '/media/images/ao-gym-nam.jpg']);

        $this->assertFileExists(public_path('media/images/ao-gym-nam.jpg'));
        $this->assertSame(asset('media/images/ao-gym-nam.jpg'), $product->imageSrc());
    }

    public function test_missing_media_file_does_not_emit_a_url(): void
    {
        $product = new Product(['image_url' => '/media/images/khong-co-anh.jpg']);

        $this->assertNull($product->imageSrc());
    }

    public function test_remote_url_is_used_as_stored(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/webthethao/products/ao.jpg';
        $product = new Product(['image_url' => $url]);

        $this->assertSame($url, $product->imageSrc());
    }

    public function test_uploaded_storage_path_stays_under_storage(): void
    {
        $product = new Product(['image_url' => 'products/demo.jpg']);

        $this->assertSame(asset('storage/products/demo.jpg'), $product->imageSrc());
    }
}
