<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gudang\ProductionPlanRequest;
use App\Models\ProductionPlan;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Services\PlanningService;
use App\Services\FormulaService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionPlanController extends Controller
{
    protected PlanningService $planningService;
    protected FormulaService $formulaService;
    protected AuditLogService $auditLogService;

    public function __construct(
        PlanningService $planningService,
        FormulaService $formulaService,
        AuditLogService $auditLogService
    ) {
        $this->planningService = $planningService;
        $this->formulaService = $formulaService;
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $query = ProductionPlan::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $plans = $query->orderBy('created_at', 'desc')->paginate(15);
        $products = Product::all();
        $lines = ProductionLine::active()->get();

        if ($request->ajax()) {
            return response()->json($plans);
        }

        return view('gudang.production-plans', compact('plans', 'products', 'lines'));
    }

    public function store(ProductionPlanRequest $request)
    {
        try {
            $plan = $this->planningService->createPlan($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Planning produksi berhasil dibuat',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(ProductionPlanRequest $request, ProductionPlan $productionPlan)
    {
        try {
            DB::beginTransaction();

            if (!$productionPlan->isDraft()) {
                throw new \Exception('Hanya planning dengan status Draft yang dapat diubah');
            }

            $oldQty = $productionPlan->quantity;
            $newQty = $request->quantity;

            $productionPlan->update([
                'quantity' => $newQty,
                'remaining_qty' => $newQty - ($productionPlan->quantity - $productionPlan->remaining_qty),
                'priority' => $request->priority,
                'plan_date' => $request->plan_date,
                'notes' => $request->notes,
            ]);

            $this->auditLogService->logWithUser(
                'Edit',
                'Planning',
                "Planning {$productionPlan->plan_number} diperbarui (qty: {$oldQty} → {$newQty})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planning berhasil diperbarui',
                'data' => $productionPlan
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(ProductionPlan $productionPlan)
    {
        try {
            DB::beginTransaction();

            if (!$productionPlan->isDraft()) {
                throw new \Exception('Hanya planning dengan status Draft yang dapat dihapus');
            }

            $this->auditLogService->logWithUser(
                'Hapus',
                'Planning',
                "Planning {$productionPlan->plan_number} dihapus"
            );

            $productionPlan->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planning berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function sendToProduction(Request $request, ProductionPlan $productionPlan)
    {
        try {
            $request->validate([
                'distributions' => 'required|array|min:1',
                'distributions.*.line_id' => 'required|exists:production_lines,id',
                'distributions.*.quantity' => 'required|integer|min:1',
                'distributions.*.distribution_date' => 'nullable|date',
            ]);

            $plan = $this->planningService->sendToProduction(
                $productionPlan->id,
                $request->distributions
            );

            return response()->json([
                'success' => true,
                'message' => 'Planning berhasil dikirim ke produksi',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function continuePlan(Request $request, ProductionPlan $productionPlan)
    {
        try {
            $request->validate([
                'distributions' => 'required|array|min:1',
                'distributions.*.line_id' => 'required|exists:production_lines,id',
                'distributions.*.quantity' => 'required|integer|min:1',
                'distributions.*.distribution_date' => 'nullable|date',
            ]);

            $plan = $this->planningService->continuePlan(
                $productionPlan->id,
                $request->distributions
            );

            return response()->json([
                'success' => true,
                'message' => 'Planning berhasil dilanjutkan',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melanjutkan planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelPlan(ProductionPlan $productionPlan)
    {
        try {
            $plan = $this->planningService->cancelPlan($productionPlan->id);

            return response()->json([
                'success' => true,
                'message' => 'Planning berhasil dibatalkan',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan planning: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDistributions(ProductionPlan $productionPlan)
    {
        $distributions = $this->planningService->getPlanDistributions($productionPlan->id);
        return response()->json($distributions);
    }

    public function calculateMaterials(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $requirements = $this->planningService->calculateMaterials(
            $request->product_id,
            $request->quantity
        );

        return response()->json($requirements);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $plans = ProductionPlan::search($keyword)->limit(20)->get();
        return response()->json($plans);
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