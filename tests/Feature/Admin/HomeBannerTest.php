<?php

namespace Tests\Feature\Admin;

use App\Domain\Content\Models\HomeBanner;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_home_banner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.home-banner.update'), [
            'image' => UploadedFile::fake()->createWithContent('banner.jpg', base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
            )),
        ])->assertRedirect();

        $banner = HomeBanner::current();
        $this->assertNotNull($banner);
        $this->assertNotSame('', $banner->image_url);
        Storage::disk('public')->assertExists($banner->image_url);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('storage/'.$banner->image_url), false);
    }

    public function test_admin_can_reset_home_banner_to_default(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = UploadedFile::fake()->createWithContent('banner.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
        ))->store('banners', 'public');
        HomeBanner::query()->create(['image_url' => $path]);

        $this->actingAs($admin)->delete(route('admin.home-banner.destroy'))->assertRedirect();

        $this->assertNull(HomeBanner::current());
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('media/images/home-banner.png'), false);
    }
}
