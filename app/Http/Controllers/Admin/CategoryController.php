<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Product\Models\Category;
use App\Domain\Product\Services\ProductWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private ProductWriter $writer) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->writer->createCategory($request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('admin.categories.index')->with('success', 'Đã thêm danh mục.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->writer->updateCategory($category, $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('admin.categories.index')->with('success', 'Đã cập nhật danh mục.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->writer->deleteCategory($category);

        return redirect()->route('admin.categories.index')->with('success', 'Đã xóa danh mục.');
    }
}
