<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produksi\FinishGoodRequest;
use App\Models\FinishGood;
use App\Models\ProductionPlan;
use App\Models\ProductionLine;
use App\Models\Product;
use App\Services\FinishGoodService;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinishGoodController extends Controller
{
    protected FinishGoodService $finishGoodService;
    protected AuditLogService $auditLogService;

    public function __construct(
        FinishGoodService $finishGoodService,
        AuditLogService $auditLogService
    ) {
        $this->finishGoodService = $finishGoodService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of finish goods.
     */
    public function index(Request $request)
    {
        $query = FinishGood::with(['product', 'plan', 'line']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('plan_id')) {
            $query->byPlan($request->plan_id);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('line_id')) {
            $query->byLine($request->line_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('qc_status')) {
            $query->byQCStatus($request->qc_status);
        }

        $finishGoods = $query->orderBy('created_at', 'desc')->paginate(15);
        $plans = ProductionPlan::whereIn('status', ['Proses', 'Selesai'])->get();
        $products = Product::all();
        $lines = ProductionLine::active()->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $finishGoods->items(),
                'current_page' => $finishGoods->currentPage(),
                'last_page' => $finishGoods->lastPage(),
                'total' => $finishGoods->total(),
            ]);
        }

        return view('produksi.finish-goods', compact('finishGoods', 'plans', 'products', 'lines'));
    }

    /**
     * Store a newly created finish good.
     */
    public function store(FinishGoodRequest $request)
    {
        try {
            $finishGood = $this->finishGoodService->createFinishGood($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Finish Good berhasil dikirim ke WHF',
                'data' => $finishGood
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim Finish Good: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified finish good.
     */
    public function update(FinishGoodRequest $request, FinishGood $finishGood)
    {
        try {
            DB::beginTransaction();

            $oldQty = $finishGood->quantity;
            $newQty = $request->quantity;
            $diff = $newQty - $oldQty;

            // Update stock
            if ($diff > 0) {
                $this->finishGoodService->addStock($finishGood->product_id, $diff);
            } elseif ($diff < 0) {
                $this->finishGoodService->subtractStock($finishGood->product_id, abs($diff));
            }

            $finishGood->update([
                'delivery_number' => $request->delivery_number,
                'pic' => $request->pic,
                'quantity' => $newQty,
                'qc_status' => $request->qc_status,
                'finish_date' => $request->finish_date,
                'notes' => $request->notes,
            ]);

            // Update plan remaining quantity
            $plan = $finishGood->plan;
            if ($plan) {
                $plan->remaining_qty = $plan->quantity - FinishGood::where('plan_id', $plan->id)->sum('quantity');
                $plan->status = $plan->remaining_qty > 0 ? 'Proses' : 'Selesai';
                $plan->save();
            }

            $this->auditLogService->logWithUser(
                'Edit',
                'Finish Good',
                "Finish Good {$finishGood->finish_number} diperbarui (qty: {$oldQty} → {$newQty})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Finish Good berhasil diperbarui',
                'data' => $finishGood
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Finish Good: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified finish good.
     */
    public function destroy(FinishGood $finishGood)
    {
        try {
            DB::beginTransaction();

            // Reverse stock
            $this->finishGoodService->subtractStock($finishGood->product_id, $finishGood->quantity);

            $this->auditLogService->logWithUser(
                'Hapus',
                'Finish Good',
                "Finish Good {$finishGood->finish_number} dihapus"
            );

            $finishGood->delete();

            // Update plan
            $plan = $finishGood->plan;
            if ($plan) {
                $plan->remaining_qty = $plan->quantity - FinishGood::where('plan_id', $plan->id)->sum('quantity');
                $plan->status = $plan->remaining_qty > 0 ? 'Proses' : 'Selesai';
                $plan->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Finish Good berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Finish Good: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * IMPORT FINISH GOODS FROM EXCEL
     * HANYA SATU METHOD import() - TIDAK ADA DUPLIKAT
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importFinishGoods($request->file('file'));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? []
        ], 500);
    }

    /**
     * Export Finish Goods to Excel.
     */
    public function export(Request $request)
    {
        // Export functionality - akan diimplementasikan di Phase 7
        return response()->json([
            'success' => false,
            'message' => 'Export functionality coming soon'
        ], 501);
    }

    /**
     * Search finish goods.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $finishGoods = FinishGood::search($keyword)->limit(20)->get();
        return response()->json($finishGoods);
    }

    /**
     * Generate finish number.
     */
    public function generateNumber(Request $request)
    {
        $number = $this->finishGoodService->generateFinishNumber();
        return response()->json(['finish_number' => $number]);
    }

    /**
     * Get plan info for finish good form.
     */
    public function getPlanInfo(ProductionPlan $plan)
    {
        return response()->json([
            'remaining_qty' => $plan->remaining_qty,
            'product_id' => $plan->product_id,
            'product_name' => $plan->product->name ?? '',
            'plan_number' => $plan->plan_number
        ]);
    }

    /**
     * Validate quantity against plan remaining.
     */
    public function validateQty(ProductionPlan $plan, $qty)
    {
        $valid = $qty <= $plan->remaining_qty && $qty > 0;
        return response()->json([
            'valid' => $valid,
            'message' => $valid ? 'Qty valid' : 'Qty melebihi sisa planning (' . $plan->remaining_qty . ' pcs)',
            'max_qty' => $plan->remaining_qty
        ]);
    }

    /**
     * Send finish good to WHF (already handled in store).
     */
    public function sendToWhf(FinishGood $finishGood)
    {
        return response()->json([
            'success' => true,
            'message' => 'Finish Good sudah dikirim ke WHF'
        ]);
    }
}