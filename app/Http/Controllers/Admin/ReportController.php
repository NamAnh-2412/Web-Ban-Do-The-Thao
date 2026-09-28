<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Report\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        return view('admin.reports.index', $this->reports->summarize($from, $to));
    }
}
