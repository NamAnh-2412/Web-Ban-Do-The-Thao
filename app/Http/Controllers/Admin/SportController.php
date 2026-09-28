<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Product\Models\Sport;
use App\Domain\Product\Services\ProductWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportController extends Controller
{
    public function __construct(private ProductWriter $writer) {}

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
        $this->writer->createSport($request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('admin.sports.index')->with('success', 'Đã thêm môn thể thao.');
    }

    public function edit(Sport $sport): View
    {
        return view('admin.sports.edit', compact('sport'));
    }

    public function update(Request $request, Sport $sport): RedirectResponse
    {
        $this->writer->updateSport($sport, $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]));

        return redirect()->route('admin.sports.index')->with('success', 'Đã cập nhật môn thể thao.');
    }

    public function destroy(Sport $sport): RedirectResponse
    {
        $this->writer->deleteSport($sport);

        return redirect()->route('admin.sports.index')->with('success', 'Đã xóa môn thể thao.');
    }
}
