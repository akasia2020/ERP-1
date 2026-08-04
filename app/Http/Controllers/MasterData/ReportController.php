<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductionLine;
use App\Models\Customer;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display master data reports page
     */
    public function index(Request $request)
    {
        // Get summary data
        $totalMaterials = Material::count();
        $totalProducts = Product::count();
        $totalSuppliers = Supplier::count();
        $totalLines = ProductionLine::count();
        $totalCustomers = Customer::count();

        // Stock status
        $lowStock = Material::whereRaw('stock_current < stock_minimum')->count();
        $emptyStock = Material::where('stock_current', '<=', 0)->count();
        $healthyStock = Material::whereRaw('stock_current >= stock_minimum')->count();

        // Get all materials for report table
        $materials = Material::with(['supplier'])
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                $status = $this->getStockStatus($item->stock_current, $item->stock_minimum);
                return [
                    'code' => $item->code,
                    'name' => $item->name,
                    'category' => $item->specification ?? '-',
                    'unit' => $item->unit,
                    'stock' => $item->stock_current,
                    'status_text' => $status['status'],
                    'status_class' => $status['badge'],
                ];
            });

        // Get all products for report table
        $products = Product::orderBy('sku')
            ->get()
            ->map(function ($item) {
                $minStock = \App\Models\Setting::getGlobalStockMin();
                $status = $this->getProductStockStatus($item->stock_current ?? 0, $minStock);
                return [
                    'code' => $item->sku,
                    'name' => $item->name,
                    'category' => $item->category ?? '-',
                    'unit' => $item->unit ?? 'Pcs',
                    'stock' => $item->stock_current ?? 0,
                    'status_text' => $status['status'],
                    'status_class' => $status['badge'],
                ];
            });

        // Combine for report
        $reportData = collect($materials)->merge($products);

        $data = [
            'totalMaterials' => $totalMaterials,
            'totalProducts' => $totalProducts,
            'totalSuppliers' => $totalSuppliers,
            'totalLines' => $totalLines,
            'totalCustomers' => $totalCustomers,
            'lowStock' => $lowStock,
            'emptyStock' => $emptyStock,
            'healthyStock' => $healthyStock,
            'reportData' => $reportData,
        ];

        // Log report view
        $this->auditLogService->logWithUser(
            'View',
            'Report',
            'Master Data report viewed'
        );

        return view('masterdata.reports', $data);
    }

    /**
     * Get report data via AJAX
     */
    public function getData(Request $request)
    {
        $type = $request->get('type', 'all');
        $search = $request->get('search', '');

        $data = [];

        if ($type === 'all' || $type === 'material') {
            $materials = Material::with(['supplier']);
            if ($search) {
                $materials->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                      ->orWhere('name', 'LIKE', "%{$search}%");
                });
            }
            $data['materials'] = $materials->orderBy('code')->get()->map(function ($item) {
                $status = $this->getStockStatus($item->stock_current, $item->stock_minimum);
                return [
                    'code' => $item->code,
                    'name' => $item->name,
                    'specification' => $item->specification ?? '-',
                    'unit' => $item->unit,
                    'stock' => $item->stock_current,
                    'status' => $status['status'],
                    'status_class' => $status['badge'],
                ];
            });
        }

        if ($type === 'all' || $type === 'product') {
            $products = Product::query();
            if ($search) {
                $products->where(function ($q) use ($search) {
                    $q->where('sku', 'LIKE', "%{$search}%")
                      ->orWhere('name', 'LIKE', "%{$search}%");
                });
            }
            $minStock = \App\Models\Setting::getGlobalStockMin();
            $data['products'] = $products->orderBy('sku')->get()->map(function ($item) use ($minStock) {
                $status = $this->getProductStockStatus($item->stock_current ?? 0, $minStock);
                return [
                    'code' => $item->sku,
                    'name' => $item->name,
                    'category' => $item->category ?? '-',
                    'unit' => $item->unit ?? 'Pcs',
                    'stock' => $item->stock_current ?? 0,
                    'status' => $status['status'],
                    'status_class' => $status['badge'],
                ];
            });
        }

        // Summary
        $data['summary'] = [
            'total_material' => Material::count(),
            'total_product' => Product::count(),
            'low_stock' => Material::whereRaw('stock_current < stock_minimum')->count(),
            'empty_stock' => Material::where('stock_current', '<=', 0)->count(),
        ];

        return response()->json($data);
    }

    /**
     * Export report to Excel
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'all');
        
        // Log export activity
        $this->auditLogService->logWithUser(
            'Export',
            'Report',
            "Master Data report exported: {$type}"
        );

        // Return CSV for now (Excel implementation will be added later)
        $data = $this->getExportData($type);
        
        if (empty($data)) {
            return response()->json(['error' => 'No data to export'], 404);
        }

        $filename = 'master_data_report_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        // Add header
        fputcsv($handle, ['Kode', 'Nama', 'Kategori', 'Satuan', 'Stock', 'Status']);
        
        // Add data
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['code'],
                $row['name'],
                $row['category'],
                $row['unit'],
                $row['stock'],
                $row['status']
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Print report
     */
    public function print(Request $request)
    {
        $type = $request->get('type', 'all');
        
        // Log print activity
        $this->auditLogService->logWithUser(
            'Print',
            'Report',
            "Master Data report printed: {$type}"
        );

        $data = $this->getExportData($type);
        
        return view('masterdata.print', [
            'data' => $data,
            'title' => 'Laporan Master Data',
            'date' => now()->format('d-m-Y H:i'),
            'user' => auth()->user()->name ?? 'System'
        ]);
    }

    /**
     * Get stock status helper
     */
    protected function getStockStatus($current, $minimum): array
    {
        if ($current <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($current < ($minimum * 0.3)) {
            return ['status' => 'Kritis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($current < $minimum) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }

    /**
     * Get product stock status helper
     */
    protected function getProductStockStatus($current, $minStock): array
    {
        if ($current <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($current < ($minStock * 0.3)) {
            return ['status' => 'Kritis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($current < $minStock) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }

    /**
     * Get export data
     */
    protected function getExportData($type): array
    {
        $data = [];

        if ($type === 'all' || $type === 'material') {
            $materials = Material::orderBy('code')->get();
            foreach ($materials as $item) {
                $status = $this->getStockStatus($item->stock_current, $item->stock_minimum);
                $data[] = [
                    'code' => $item->code,
                    'name' => $item->name,
                    'category' => $item->specification ?? '-',
                    'unit' => $item->unit,
                    'stock' => $item->stock_current,
                    'status' => $status['status'],
                ];
            }
        }

        if ($type === 'all' || $type === 'product') {
            $minStock = \App\Models\Setting::getGlobalStockMin();
            $products = Product::orderBy('sku')->get();
            foreach ($products as $item) {
                $status = $this->getProductStockStatus($item->stock_current ?? 0, $minStock);
                $data[] = [
                    'code' => $item->sku,
                    'name' => $item->name,
                    'category' => $item->category ?? '-',
                    'unit' => $item->unit ?? 'Pcs',
                    'stock' => $item->stock_current ?? 0,
                    'status' => $status['status'],
                ];
            }
        }

        return $data;
    }
}