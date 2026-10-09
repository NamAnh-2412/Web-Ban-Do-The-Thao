<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\HomeBanner;
use App\Gateway\Media\CloudinaryImageStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeBannerController extends Controller
{
    public function __construct(private CloudinaryImageStore $images) {}

    public function edit(): View
    {
        $banner = HomeBanner::current();

        return view('admin.home-banner.edit', [
            'banner' => $banner,
            'previewUrl' => HomeBanner::displayUrl(),
            'usingDefault' => $banner === null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ], [
            'image.required' => 'Chọn ảnh banner trang chủ.',
            'image.image' => 'File phải là ảnh.',
            'image.max' => 'Ảnh tối đa 5MB.',
        ]);

        $path = $this->storeImage($request);
        $row = HomeBanner::current();
        $previous = $row?->image_url;

        if ($row) {
            $row->update(['image_url' => $path]);
        } else {
            HomeBanner::query()->create(['image_url' => $path]);
        }

        if ($previous && $previous !== $path) {
            $this->deleteImage($previous);
        }

        return back()->with('success', 'Đã cập nhật ảnh banner trang chủ.');
    }

    public function destroy(): RedirectResponse
    {
        $row = HomeBanner::current();
        if ($row) {
            $this->deleteImage($row->image_url);
            $row->delete();
        }

        return back()->with('success', 'Đã trở lại ảnh banner mặc định.');
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');

        if ($this->images->configured()) {
            return $this->images->upload($file, 'webthethao/banners');
        }

        return $file->store('banners', 'public');
    }

    private function deleteImage(?string $imageUrl): void
    {
        if ($imageUrl === null || $imageUrl === '') {
            return;
        }

        if (str_contains($imageUrl, 'res.cloudinary.com')) {
            $this->images->delete($imageUrl);

            return;
        }

        if (! str_starts_with($imageUrl, 'http') && ! str_starts_with($imageUrl, '/')) {
            Storage::disk('public')->delete($imageUrl);
        }
    }
}
