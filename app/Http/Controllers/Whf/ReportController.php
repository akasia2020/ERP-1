<?php

namespace App\Http\Controllers\Whf;

use App\Http\Controllers\Controller;
use App\Models\WhfStock;
use App\Models\WhfIncoming;
use App\Models\WhfOutgoing;
use App\Services\WhfService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected WhfService $whfService;

    public function __construct(WhfService $whfService)
    {
        $this->whfService = $whfService;
    }

    public function index(Request $request)
    {
        return view('whf.reports');
    }

    public function getData(Request $request)
    {
        $query = WhfStock::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        $data = $query->get();

        foreach ($data as $item) {
            $item->total_in = WhfIncoming::where('product_id', $item->product_id)->sum('quantity');
            $item->total_out = WhfOutgoing::where('product_id', $item->product_id)->sum('quantity');
            $item->status = $this->getStockStatus($item->stock_current);
        }

        return response()->json($data);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    public function print(Request $request)
    {
        // Print functionality
        return response()->json(['message' => 'Print functionality coming soon']);
    }

    protected function getStockStatus($stock)
    {
        if ($stock <= 0) {
            return ['status' => 'Habis', 'class' => 'badge-danger'];
        }
        if ($stock < 100) {
            return ['status' => 'Menipis', 'class' => 'badge-warning'];
        }
        if ($stock < 500) {
            return ['status' => 'Sedang', 'class' => 'badge-info'];
        }
        return ['status' => 'Aman', 'class' => 'badge-success'];
    }
}