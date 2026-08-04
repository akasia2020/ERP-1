<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Product;
use App\Models\ProductionLine;
use Illuminate\Http\Request;

class ProductionReportController extends Controller{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_id' => $request->input('product_id'),
            'status' => $request->input('status'),
            'line_id' => $request->input('line_id'),
        ];

        $report = $this->reportService->getProductionReport($filters);

        $products = Product::all();
        $lines = ProductionLine::all();

        return view('reports.production', compact(
            'report',
            'products',
            'lines',
            'filters'
        ));
    }

    public function getData(Request $request)
    {
        $filters = $request->all();
        $report = $this->reportService->getProductionReport($filters);

        return response()->json($report);
    }
}