<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Review\Models\Review;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.reviews.index', [
            'reviews' => Review::query()->with(['user', 'product'])->orderByDesc('id')->paginate(20),
        ]);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Đã xóa đánh giá.');
    }
}
