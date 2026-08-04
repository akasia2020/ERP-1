<?php

namespace App\Http\Controllers\Whf;

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
            ->whereIn('status', ['Proses', 'Selesai']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $plans = $query->orderBy('created_at', 'desc')->paginate(15);

        foreach ($plans as $plan) {
            $distributions = PlanDistribution::with(['line'])
                ->where('plan_id', $plan->id)
                ->get();

            $plan->distributions = $distributions;
            
            $totalFinished = FinishGood::where('plan_id', $plan->id)->sum('quantity');
            $plan->progress = $plan->quantity > 0 
                ? round(($totalFinished / $plan->quantity) * 100, 2) 
                : 0;
        }

        if ($request->ajax()) {
            return response()->json($plans);
        }

        return view('whf.production-running', compact('plans'));
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

        return response()->json([
            'plan' => $plan,
            'finish_goods' => $finishGoods,
            'total_finished' => $totalFinished,
            'progress' => $progress
        ]);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $plans = ProductionPlan::search($keyword)
            ->whereIn('status', ['Proses', 'Selesai'])
            ->limit(20)
            ->get();
        return response()->json($plans);
    }
}