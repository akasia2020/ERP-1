<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Models\FinishGood;
use App\Models\ProductionPlan;
use App\Models\PlanDistribution;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('produksi.reports');
    }

    public function getData(Request $request)
    {
        $query = FinishGood::with(['product', 'plan', 'line']);

        if ($request->filled('line')) {
            $query->whereHas('line', function ($q) use ($request) {
                $q->where('name', $request->line);
            });
        }

        if ($request->filled('kode')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sku', 'LIKE', "%{$request->kode}%");
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $data = $query->orderBy('finish_date', 'desc')
            ->limit(100)
            ->get();

        return response()->json($data);
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