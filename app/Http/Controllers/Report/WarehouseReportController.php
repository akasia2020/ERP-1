<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Material;
use Illuminate\Http\Request;

class WarehouseReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->format('Y-m-d')),
            'material_id' => $request->input('material_id'),
        ];

        $report = $this->reportService->getWarehouseReport($filters);
        $materials = Material::all();

        return view('reports.warehouse', compact(
            'report',
            'materials',
            'filters'
        ));
    }

    public function getData(Request $request)
    {
        $filters = $request->all();
        $report = $this->reportService->getWarehouseReport($filters);

        return response()->json($report);
    }
}