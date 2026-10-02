<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Product\Models\Sport;
use App\Domain\Product\Services\ProductWriter;
use App\Gateway\Media\CloudinaryImageStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator as LaravelValidator;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ProductWriter $writer,
        private CloudinaryImageStore $images,
    ) {}

    public function index(): View
    {
        $products = Product::query()
            ->with(['category', 'variants.stock'])
            ->withCount('variants')
            ->orderByDesc('id')
            ->get();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image_url'] = $this->storeImage($request);
        $product = $this->writer->saveWithVariants($data);

        return redirect()->route('admin.products.edit', $product)->with('success', 'Đã thêm sản phẩm.');
    }

    public function edit(Product $product): View
    {
        $product->load(['variants' => fn ($q) => $q->with('stock')->withCount('items')]);

        return view('admin.products.edit', $this->formData() + compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $previous = $product->image_url;
        if ($request->file('image')) {
            $data['image_url'] = $this->storeImage($request);
        }
        $this->writer->saveWithVariants($data, $product);
        if (isset($data['image_url']) && $data['image_url'] !== $previous) {
            $this->deleteImage($previous);
        }

        return redirect()->route('admin.products.edit', $product)->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteImage($product->image_url);
        $this->writer->deleteProduct($product);

        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    private function storeImage(Request $request): ?string
    {
        $file = $request->file('image');
        if ($file === null) {
            return null;
        }

        if ($this->images->configured()) {
            return $this->images->upload($file);
        }

        return $file->store('products', 'public');
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

    /** @return array{categories: Collection, sports: Collection} */
    private function formData(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(),
            'sports' => Sport::query()->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'offer_mode' => ['required', 'in:sale,rental,both'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'variants.*.size' => ['nullable', 'string', 'max:30'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.rental_price_per_day' => ['nullable', 'numeric', 'min:0'],
            'variants.*.deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'variants.*.quantity' => ['required', 'integer', 'min:0'],
            'variants.*.rental_quantity' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Vui lòng nhập tên sản phẩm.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'variants.required' => 'Sản phẩm phải có ít nhất một phân loại.',
            'variants.*.sku.required' => 'Vui lòng nhập mã SKU.',
            'variants.*.sku.distinct' => 'Mã SKU không được trùng nhau.',
            'variants.*.quantity.required' => 'Vui lòng nhập tồn kho.',
        ]);

        $validator->after(function (LaravelValidator $validator) use ($request) {
            foreach ($request->input('variants', []) as $index => $variant) {
                $sku = trim((string) ($variant['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $variantId = isset($variant['id']) ? (int) $variant['id'] : null;
                $exists = ProductVariant::query()
                    ->where('sku', $sku)
                    ->when($variantId, fn ($q) => $q->whereKeyNot($variantId))
                    ->exists();
                if ($exists) {
                    $validator->errors()->add("variants.$index.sku", 'Mã SKU đã được sử dụng.');
                }
            }
        });

        return $validator->validate();
    }
}
