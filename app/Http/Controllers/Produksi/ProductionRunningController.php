<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Models\ProductionPlan;
use App\Models\PlanDistribution;
use App\Models\FinishGood;
use Illuminate\Http\Request;

class ProductionRunningController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionPlan::with(['product'])
            ->whereIn('status', ['Draft', 'Proses', 'Selesai']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        $plans = $query->orderBy('created_at', 'desc')->paginate(15);

        // Calculate distribution info for each plan
        foreach ($plans as $plan) {
            $distributions = PlanDistribution::with(['line'])
                ->where('plan_id', $plan->id)
                ->get();

            $plan->distributions = $distributions;

            // Calculate progress
            $totalDistributed = $distributions->sum('quantity');
            $plan->progress = $plan->quantity > 0 
                ? round(($totalDistributed / $plan->quantity) * 100, 2) 
                : 0;

            // Get line details for display
            $lineDetails = [];
            foreach ($distributions as $dist) {
                $finishedQty = FinishGood::where('plan_id', $plan->id)
                    ->where('line_id', $dist->line_id)
                    ->sum('quantity');

                $lineDetails[] = [
                    'line_name' => $dist->line->name ?? 'Unknown',
                    'line_code' => $dist->line->code ?? '',
                    'assigned_qty' => $dist->quantity,
                    'finished_qty' => $finishedQty,
                    'remaining' => $dist->quantity - $finishedQty,
                    'status' => $finishedQty >= $dist->quantity ? 'Selesai' : 'Running'
                ];
            }
            $plan->line_details = $lineDetails;
        }

        if ($request->ajax()) {
            return response()->json($plans);
        }

        return view('produksi.production-running', compact('plans'));
    }

    public function detail($planId)
    {
        $plan = ProductionPlan::with(['product', 'distributions.line'])
            ->findOrFail($planId);

        $finishGoods = FinishGood::with(['line'])
            ->where('plan_id', $planId)
            ->get();

        $totalFinished = $finishGoods->sum('quantity');
        $progress = $plan->quantity > 0 
            ? round(($totalFinished / $plan->quantity) * 100, 2) 
            : 0;

        $distributions = [];
        foreach ($plan->distributions as $dist) {
            $finishedQty = $finishGoods->where('line_id', $dist->line_id)->sum('quantity');
            $distributions[] = [
                'line_name' => $dist->line->name ?? 'Unknown',
                'line_code' => $dist->line->code ?? '',
                'assigned_qty' => $dist->quantity,
                'finished_qty' => $finishedQty,
                'remaining' => $dist->quantity - $finishedQty,
                'status' => $finishedQty >= $dist->quantity ? 'Selesai' : 'Running'
            ];
        }

        return response()->json([
            'plan' => $plan,
            'finish_goods' => $finishGoods,
            'total_finished' => $totalFinished,
            'progress' => $progress,
            'distributions' => $distributions
        ]);
    }

    public function getLines($planId)
    {
        $distributions = PlanDistribution::with(['line'])
            ->where('plan_id', $planId)
            ->get();

        $result = [];
        foreach ($distributions as $dist) {
            $finishedQty = FinishGood::where('plan_id', $planId)
                ->where('line_id', $dist->line_id)
                ->sum('quantity');

            $result[] = [
                'line_id' => $dist->line_id,
                'line_name' => $dist->line->name ?? 'Unknown',
                'line_code' => $dist->line->code ?? '',
                'assigned_qty' => $dist->quantity,
                'finished_qty' => $finishedQty,
                'remaining' => $dist->quantity - $finishedQty,
                'status' => $finishedQty >= $dist->quantity ? 'Selesai' : 'Running'
            ];
        }

        return response()->json($result);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $plans = ProductionPlan::search($keyword)
            ->whereIn('status', ['Draft', 'Proses', 'Selesai'])
            ->limit(20)
            ->get();
        return response()->json($plans);
    }
}