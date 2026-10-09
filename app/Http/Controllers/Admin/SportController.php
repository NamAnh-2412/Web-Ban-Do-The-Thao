<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Product\Models\Sport;
use App\Domain\Product\Services\ProductWriter;
use App\Gateway\Media\CloudinaryImageStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SportController extends Controller
{
    public function __construct(
        private ProductWriter $writer,
        private CloudinaryImageStore $images,
    ) {}

    public function index(): View
    {
        return view('admin.sports.index', [
            'sports' => Sport::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($path = $this->storeImage($request)) {
            $data['image_url'] = $path;
        }

        $this->writer->createSport($data);

        return redirect()->route('admin.sports.index')->with('success', 'Đã thêm môn thể thao.');
    }

    public function edit(Sport $sport): View
    {
        return view('admin.sports.edit', compact('sport'));
    }

    public function update(Request $request, Sport $sport): RedirectResponse
    {
        $data = $this->validated($request, $sport);
        $previous = $sport->image_url;

        if ($request->file('image')) {
            $data['image_url'] = $this->storeImage($request);
        }

        $this->writer->updateSport($sport, $data);

        if (isset($data['image_url']) && $data['image_url'] !== $previous) {
            $this->deleteImage($previous);
        }

        return redirect()->route('admin.sports.index')->with('success', 'Đã cập nhật môn thể thao.');
    }

    public function destroy(Sport $sport): RedirectResponse
    {
        $this->deleteImage($sport->image_url);
        $this->writer->deleteSport($sport);

        return redirect()->route('admin.sports.index')->with('success', 'Đã xóa môn thể thao.');
    }

    /** @return array{name: string, headline: ?string, description: ?string} */
    private function validated(Request $request, ?Sport $sport = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:5120'],
        ], [
            'name.required' => 'Nhập tên môn thể thao.',
            'image.image' => 'File phải là ảnh.',
            'image.max' => 'Ảnh tối đa 5MB.',
        ]);

        unset($data['image']);

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        $file = $request->file('image');
        if ($file === null) {
            return null;
        }

        if ($this->images->configured()) {
            return $this->images->upload($file, 'webthethao/sports');
        }

        return $file->store('sports', 'public');
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
