<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        $summary = $this->reportService->getDashboardSummary();
        $productionTrend = $this->reportService->getProductionTrend();
        $stockMovement = $this->reportService->getStockMovementTrend();
        $incomingOutgoing = $this->reportService->getIncomingOutgoingSummary();

        return view('reports.dashboard', compact(
            'summary',
            'productionTrend',
            'stockMovement',
            'incomingOutgoing'
        ));
    }

    public function getData(Request $request)
    {
        $data = [
            'summary' => $this->reportService->getDashboardSummary(),
            'production_trend' => $this->reportService->getProductionTrend(),
            'stock_movement' => $this->reportService->getStockMovementTrend(),
            'incoming_outgoing' => $this->reportService->getIncomingOutgoingSummary(),
        ];

        return response()->json($data);
    }
}