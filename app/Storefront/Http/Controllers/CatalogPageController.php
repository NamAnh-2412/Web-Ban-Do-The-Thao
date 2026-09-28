<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Product\Http\Requests\CatalogProductIndexRequest;
use App\Domain\Product\Services\CatalogService;
use App\Domain\Review\Services\ReviewWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogPageController extends Controller
{
    public function __construct(
        private CatalogService $catalog,
        private ReviewWriter $reviews,
    ) {}

    public function home(): View
    {
        return view('storefront.home', [
            'sports' => $this->catalog->sports(),
            'categories' => $this->catalog->categories(),
            'products' => $this->catalog->products(['limit' => 8]),
            'bestSellers' => $this->catalog->bestSellers(8),
        ]);
    }

    public function index(CatalogProductIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('storefront.catalog', [
            'sports' => $this->catalog->sports(),
            'categories' => $this->catalog->categories(),
            'products' => $this->catalog->products($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = $this->catalog->productBySlug($slug);

        $reviews = $this->reviews->listByProduct($product->id);
        $requestedMode = $request->query('offer_mode');
        $requestedMode = in_array($requestedMode, ['sale', 'rental'], true) ? $requestedMode : null;

        return view('storefront.product', [
            'product' => $product,
            'reviews' => $reviews,
            'reviewAvg' => round((float) $reviews->avg('rating'), 1),
            'requestedMode' => $requestedMode,
        ]);
    }
}
