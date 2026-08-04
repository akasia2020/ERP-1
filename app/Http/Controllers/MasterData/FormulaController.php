<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\FormulaRequest;
use App\Models\Product;
use App\Models\Material;
use App\Services\FormulaService;
use Illuminate\Http\Request;

class FormulaController extends Controller
{
    protected FormulaService $formulaService;

    public function __construct(FormulaService $formulaService)
    {
        $this->formulaService = $formulaService;
    }

    public function index(Request $request)
    {
        $query = Product::with(['formula', 'formula.details', 'formula.details.material']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('sku', 'LIKE', "%{$request->search}%")
                  ->orWhere('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('category', 'LIKE', "%{$request->search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        $materials = Material::with(['supplier'])->get();

        if ($request->ajax()) {
            return response()->json($products);
        }

        return view('masterdata.formulas', compact('products', 'materials'));
    }

    public function createFormula(FormulaRequest $request)
    {
        try {
            $formula = $this->formulaService->createFormula(
                $request->product_id,
                $request->materials
            );

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil dibuat',
                'data' => $formula
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat formula: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFormula(FormulaRequest $request, $productId)
    {
        try {
            $formula = $this->formulaService->updateFormula(
                $productId,
                $request->materials
            );

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil diperbarui',
                'data' => $formula
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui formula: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFormula($productId)
    {
        try {
            $this->formulaService->deleteFormula($productId);

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus formula: ' . $e->getMessage()
            ], 500);
        }
    }

    public function details($productId)
    {
        $formula = $this->formulaService->getFormula($productId);
        return response()->json($formula);
    }

    public function calculateRequirements($productId, $quantity)
    {
        $requirements = $this->formulaService->calculateRequirements($productId, $quantity);
        return response()->json($requirements);
    }

    public function validateStock($productId, $quantity)
    {
        $result = $this->formulaService->validateStockForPlan($productId, $quantity);
        return response()->json($result);
    }

    public function import(Request $request)
    {
        // Import functionality
        return response()->json(['message' => 'Import functionality coming soon']);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }
}