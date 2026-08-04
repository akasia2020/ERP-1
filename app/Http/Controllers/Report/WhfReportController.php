<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;

class WhfReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->input('search'),
            'product_id' => $request->input('product_id'),
            'status' => $request->input('status'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'customer_id' => $request->input('customer_id'),
        ];

        $stockReport = $this->reportService->getWhfReport($filters);
        $incomingOutgoingReport = $this->reportService->getWhfIncomingOutgoingReport($filters);

        $products = Product::all();
        $customers = Customer::all();

        return view('reports.whf', compact(
            'stockReport',
            'incomingOutgoingReport',
            'products',
            'customers',
            'filters'
        ));
    }

    public function getData(Request $request)
    {
        $filters = $request->all();
        
        $data = [
            'stock' => $this->reportService->getWhfReport($filters),
            'incoming_outgoing' => $this->reportService->getWhfIncomingOutgoingReport($filters),
        ];

        return response()->json($data);
    }
}