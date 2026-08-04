<?php

namespace App\Http\Controllers\Whf;

use App\Http\Controllers\Controller;
use App\Models\WhfStock;
use App\Models\Product;
use App\Services\WhfService;
use Illuminate\Http\Request;

class ProductStockController extends Controller
{
    protected WhfService $whfService;

    public function __construct(WhfService $whfService)
    {
        $this->whfService = $whfService;
    }

    public function index(Request $request)
    {
        $query = WhfStock::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        $stocks = $query->orderBy('stock_current', 'desc')->paginate(15);

        foreach ($stocks as $stock) {
            $stock->status = $this->getStockStatus($stock->stock_current);
        }

        if ($request->ajax()) {
            return response()->json($stocks);
        }

        return view('whf.product-stocks', compact('stocks'));
    }

    public function getData(Request $request)
    {
        $stocks = WhfStock::with(['product'])->get();

        foreach ($stocks as $stock) {
            $stock->status = $this->getStockStatus($stock->stock_current);
        }

        return response()->json($stocks);
    }

    public function getMaterials($productId)
    {
        $product = Product::with(['formula.details.material'])->find($productId);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $materials = [];
        if ($product->formula) {
            foreach ($product->formula->details as $detail) {
                $material = $detail->material;
                if ($material) {
                    $materials[] = [
                        'code' => $material->code,
                        'name' => $material->name,
                        'specification' => $material->specification,
                        'unit' => $material->unit,
                        'qty_per_unit' => $detail->quantity,
                        'stock_current' => $material->stock_current,
                        'stock_minimum' => $material->stock_minimum,
                    ];
                }
            }
        }

        return response()->json($materials);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $stocks = WhfStock::search($keyword)->limit(20)->get();
        return response()->json($stocks);
    }

    protected function getStockStatus($stock)
    {
        if ($stock <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($stock < 100) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        if ($stock < 500) {
            return ['status' => 'Sedang', 'class' => 'row-stock-info', 'badge' => 'badge-info'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }
}