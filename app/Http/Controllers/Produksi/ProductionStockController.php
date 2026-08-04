<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Models\ProductionPlan;
use App\Models\FinishGood;
use App\Models\PlanDistribution;
use Illuminate\Http\Request;

class ProductionStockController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductionPlan::with(['product'])
            ->whereIn('status', ['Proses', 'Selesai']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        $plans = $query->orderBy('created_at', 'desc')->paginate(15);

        foreach ($plans as $plan) {
            // Get distributions per line
            $distributions = PlanDistribution::with(['line'])
                ->where('plan_id', $plan->id)
                ->get();

            $plan->lineA = $distributions->where('line_id', 1)->first()->quantity ?? 0;
            $plan->lineB = $distributions->where('line_id', 2)->first()->quantity ?? 0;
            $plan->lineC = $distributions->where('line_id', 3)->first()->quantity ?? 0;

            // Get finished goods
            $finishGoods = FinishGood::where('plan_id', $plan->id)->get();
            $plan->total_produced = $finishGoods->sum('quantity');
            
            // Get latest finish good details
            $latest = $finishGoods->last();
            $plan->no_sj = $latest ? $latest->delivery_number : '-';
            $plan->no_hasil = $latest ? $latest->finish_number : '-';
            
            $plan->status_text = $plan->remaining_qty > 0 ? 'Lanjutkan' : 'Selesai';
        }

        if ($request->ajax()) {
            return response()->json($plans);
        }

        return view('produksi.production-stocks', compact('plans'));
    }

    public function getData(Request $request)
    {
        $plans = ProductionPlan::with(['product'])
            ->whereIn('status', ['Proses', 'Selesai'])
            ->get();

        foreach ($plans as $plan) {
            $distributions = PlanDistribution::with(['line'])
                ->where('plan_id', $plan->id)
                ->get();

            $plan->lineA = $distributions->where('line_id', 1)->first()->quantity ?? 0;
            $plan->lineB = $distributions->where('line_id', 2)->first()->quantity ?? 0;
            $plan->lineC = $distributions->where('line_id', 3)->first()->quantity ?? 0;

            $finishGoods = FinishGood::where('plan_id', $plan->id)->get();
            $plan->total_produced = $finishGoods->sum('quantity');
            
            $latest = $finishGoods->last();
            $plan->no_sj = $latest ? $latest->delivery_number : '-';
            $plan->no_hasil = $latest ? $latest->finish_number : '-';
            
            $plan->status_text = $plan->remaining_qty > 0 ? 'Lanjutkan' : 'Selesai';
        }

        return response()->json($plans);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
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