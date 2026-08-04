<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
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
            'transaction_type' => $request->input('transaction_type'),
            'search' => $request->input('search'),
        ];

        $stockCardReport = $this->reportService->getStockCardReport($filters);
        $materialReport = $this->reportService->getMaterialStockReport($filters);
        $productReport = $this->reportService->getProductStockReport($filters);

        $products = Product::all();
        $suppliers = Supplier::all();

        return view('reports.stock', compact(
            'stockCardReport',
            'materialReport',
            'productReport',
            'products',
            'suppliers',
            'filters'
        ));
    }

    public function getData(Request $request)
    {
        $filters = $request->all();
        
        $data = [
            'stock_card' => $this->reportService->getStockCardReport($filters),
            'material' => $this->reportService->getMaterialStockReport($filters),
            'product' => $this->reportService->getProductStockReport($filters),
        ];

        return response()->json($data);
    }
}