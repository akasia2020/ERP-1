<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\StockCard;
use App\Models\Product;
use Illuminate\Http\Request;

class StockCardController extends Controller
{
    public function index(Request $request)
    {
        $query = StockCard::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_code')) {
            $query->byProductCode($request->product_code);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $stockCards = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $products = Product::all();

        if ($request->ajax()) {
            return response()->json($stockCards);
        }

        return view('gudang.stock-cards', compact('stockCards', 'products'));
    }

    public function getData(Request $request)
    {
        $query = StockCard::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_code')) {
            $query->byProductCode($request->product_code);
        }

        $stockCards = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($stockCards);
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

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $stockCards = StockCard::search($keyword)->limit(20)->get();
        return response()->json($stockCards);
    }
}