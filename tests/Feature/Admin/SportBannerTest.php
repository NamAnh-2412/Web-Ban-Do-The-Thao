<?php

namespace Tests\Feature\Admin;

use App\Domain\Product\Models\Sport;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SportBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_sport_image_headline_and_description(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $sport = Sport::factory()->create(['name' => 'Bóng đá']);

        $this->actingAs($admin)->put(route('admin.sports.update', $sport), [
            'name' => 'Bóng đá',
            'headline' => 'Bộ Sưu Tập Bóng Đá - Mùa Giải Mới Đã Về',
            'description' => 'Khám phá trang phục thi đấu chính hãng',
            'image' => UploadedFile::fake()->createWithContent('bong-da.jpg', base64_decode(
                '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
            )),
        ])->assertRedirect(route('admin.sports.index'));

        $sport->refresh();
        $this->assertSame('Bộ Sưu Tập Bóng Đá - Mùa Giải Mới Đã Về', $sport->headline);
        $this->assertSame('Khám phá trang phục thi đấu chính hãng', $sport->description);
        $this->assertNotNull($sport->image_url);
        Storage::disk('public')->assertExists($sport->image_url);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Bộ Sưu Tập Bóng Đá - Mùa Giải Mới Đã Về', false)
            ->assertSee('Khám phá trang phục thi đấu chính hãng', false)
            ->assertSee('data-sport-carousel', false)
            ->assertSee('Xem sản phẩm', false)
            ->assertSee('Chính sách thuê', false);
    }

    public function test_home_hides_carousel_when_sports_have_no_image(): void
    {
        Sport::factory()->create([
            'name' => 'Tennis',
            'image_url' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-sport-carousel', false);
    }
}
