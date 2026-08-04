<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductionPlan;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['whfStock']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);

        foreach ($products as $product) {
            // Calculate remaining production
            $totalSisa = ProductionPlan::where('product_id', $product->id)
                ->whereIn('status', ['Draft', 'Proses'])
                ->sum('remaining_qty');

            $product->sisa_produksi = $totalSisa;
            $product->info = $totalSisa > 0 
                ? '⚠️ Masih ada sisa yang belum diproduksi, hubungi line produksi' 
                : '✅ Produksi Completed';
        }

        if ($request->ajax()) {
            return response()->json($products);
        }

        return view('gudang.product-stocks', compact('products'));
    }

    public function getData(Request $request)
    {
        $products = Product::with(['whfStock'])->get();

        foreach ($products as $product) {
            $totalSisa = ProductionPlan::where('product_id', $product->id)
                ->whereIn('status', ['Draft', 'Proses'])
                ->sum('remaining_qty');

            $product->sisa_produksi = $totalSisa;
            $product->info = $totalSisa > 0 ? 'Belum Komplit' : 'Selesai';
        }

        return response()->json($products);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $products = Product::search($keyword)->limit(20)->get();
        return response()->json($products);
    }
}