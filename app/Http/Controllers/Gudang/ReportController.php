<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\StockCard;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('gudang.reports');
    }

    public function material(Request $request)
    {
        $query = Material::with(['supplier']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('kode')) {
            $query->where('code', 'LIKE', "%{$request->kode}%");
        }

        $materials = $query->orderBy('code')->paginate(15);

        // Calculate summary
        $summary = [
            'total_materials' => Material::count(),
            'total_stock' => Material::sum('stock_current'),
            'low_stock' => Material::whereRaw('stock_current < stock_minimum')->count(),
            'empty_stock' => Material::where('stock_current', '<=', 0)->count(),
        ];

        if ($request->ajax()) {
            return response()->json(['data' => $materials, 'summary' => $summary]);
        }

        return view('gudang.reports', compact('materials', 'summary'));
    }

    public function product(Request $request)
    {
        $query = Product::with(['whfStock']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('kode')) {
            $query->where('sku', 'LIKE', "%{$request->kode}%");
        }

        $products = $query->orderBy('sku')->paginate(15);

        foreach ($products as $product) {
            $totalSisa = ProductionPlan::where('product_id', $product->id)
                ->whereIn('status', ['Draft', 'Proses'])
                ->sum('remaining_qty');

            $product->sisa_produksi = $totalSisa;
        }

        $summary = [
            'total_products' => Product::count(),
            'total_stock' => Product::sum('stock_current'),
            'in_production' => ProductionPlan::whereIn('status', ['Draft', 'Proses'])->count(),
        ];

        if ($request->ajax()) {
            return response()->json(['data' => $products, 'summary' => $summary]);
        }

        return view('gudang.reports', compact('products', 'summary'));
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
}