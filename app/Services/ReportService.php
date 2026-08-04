<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\WhfStock;
use App\Models\StockCard;
use App\Models\ProductionPlan;
use App\Models\FinishGood;
use App\Models\WhfOutgoing;
use App\Models\MaterialIncoming;
use App\Models\OtherTransaction;
use App\Models\WhfIncoming;
use App\Models\ReturnModel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    // ============================================
    // DASHBOARD REPORTS
    // ============================================

    public function getDashboardSummary(): array
    {
        return [
            'total_materials' => Material::count(),
            'total_products' => Product::count(),
            'total_material_stock' => Material::sum('stock_current'),
            'total_product_stock' => Product::sum('stock_current'),
            'total_whf_stock' => WhfStock::sum('stock_current'),
            'low_stock_materials' => Material::whereRaw('stock_current < stock_minimum')->count(),
            'low_stock_products' => $this->getLowStockProducts(),
            'production_today' => $this->getProductionToday(),
            'production_this_month' => $this->getProductionThisMonth(),
            'outgoing_today' => $this->getOutgoingToday(),
        ];
    }

    public function getProductionToday(): int
    {
        return FinishGood::whereDate('finish_date', Carbon::today())->sum('quantity');
    }

    public function getProductionThisMonth(): int
    {
        return FinishGood::whereMonth('finish_date', Carbon::now()->month)
            ->whereYear('finish_date', Carbon::now()->year)
            ->sum('quantity');
    }

    public function getOutgoingToday(): int
    {
        return WhfOutgoing::whereDate('outgoing_date', Carbon::today())->sum('quantity');
    }

    public function getLowStockProducts(): int
    {
        $minStock = Setting::getGlobalStockMin();
        return Product::where('stock_current', '<', $minStock)->count();
    }

    public function getProductionTrend(int $days = 30): array
    {
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'total' => FinishGood::whereDate('finish_date', $date)->sum('quantity')
            ];
        }
        return $trend;
    }

    public function getStockMovementTrend(int $days = 30): array
    {
        $trend = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $incoming = StockCard::whereDate('transaction_date', $date)
                ->sum('quantity_in');
            $outgoing = StockCard::whereDate('transaction_date', $date)
                ->sum('quantity_out');
            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'incoming' => $incoming,
                'outgoing' => $outgoing
            ];
        }
        return $trend;
    }

    public function getIncomingOutgoingSummary(): array
    {
        return [
            'total_incoming' => WhfIncoming::sum('quantity'),
            'total_outgoing' => WhfOutgoing::sum('quantity'),
            'total_material_incoming' => MaterialIncoming::sum('quantity'),
            'total_material_used' => $this->getTotalMaterialUsed(),
        ];
    }

    protected function getTotalMaterialUsed(): int
    {
        // Calculate from production plans that have been sent to production
        $total = 0;
        $plans = ProductionPlan::whereIn('status', ['Proses', 'Selesai'])->get();
        foreach ($plans as $plan) {
            $total += $plan->quantity - $plan->remaining_qty;
        }
        return $total;
    }

    // ============================================
    // STOCK REPORTS
    // ============================================

    public function getStockCardReport(array $filters): array
    {
        $query = StockCard::with(['product']);

        if (!empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $data = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate opening and closing for each product
        $grouped = [];
        foreach ($data as $item) {
            $key = $item->product_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'product' => $item->product,
                    'transactions' => [],
                    'opening_stock' => $item->stock_before,
                    'closing_stock' => $item->stock_after,
                    'total_in' => 0,
                    'total_out' => 0,
                ];
            }
            $grouped[$key]['transactions'][] = $item;
            $grouped[$key]['total_in'] += $item->quantity_in;
            $grouped[$key]['total_out'] += $item->quantity_out;
            $grouped[$key]['closing_stock'] = $item->stock_after;
        }

        return [
            'data' => $data,
            'grouped' => $grouped,
            'summary' => [
                'total_transactions' => $data->count(),
                'total_products' => count($grouped),
                'total_in' => $data->sum('quantity_in'),
                'total_out' => $data->sum('quantity_out'),
            ]
        ];
    }

    public function getMaterialStockReport(array $filters): array
    {
        $query = Material::with(['supplier']);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'low') {
                $query->whereRaw('stock_current < stock_minimum');
            } elseif ($filters['status'] === 'empty') {
                $query->where('stock_current', '<=', 0);
            } elseif ($filters['status'] === 'normal') {
                $query->whereRaw('stock_current >= stock_minimum');
            }
        }

        $data = $query->orderBy('code')->get();

        foreach ($data as $item) {
            $status = $this->getStockStatus($item->stock_current, $item->stock_minimum);
            $item->status = $status;
            $item->total_in = MaterialIncoming::where('material_id', $item->id)->sum('quantity');
            $item->total_out = OtherTransaction::where('material_id', $item->id)->sum('quantity_out');
        }

        return [
            'data' => $data,
            'summary' => [
                'total_materials' => $data->count(),
                'total_stock' => $data->sum('stock_current'),
                'low_stock' => $data->filter(function ($item) {
                    return $item->stock_current < $item->stock_minimum;
                })->count(),
                'empty_stock' => $data->filter(function ($item) {
                    return $item->stock_current <= 0;
                })->count(),
            ]
        ];
    }

    public function getProductStockReport(array $filters): array
    {
        $query = Product::with(['whfStock']);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $minStock = Setting::getGlobalStockMin();
            if ($filters['status'] === 'low') {
                $query->where('stock_current', '<', $minStock);
            } elseif ($filters['status'] === 'empty') {
                $query->where('stock_current', '<=', 0);
            } elseif ($filters['status'] === 'normal') {
                $query->where('stock_current', '>=', $minStock);
            }
        }

        $data = $query->orderBy('sku')->get();

        $minStock = Setting::getGlobalStockMin();
        foreach ($data as $item) {
            $item->status = $this->getProductStockStatus($item->stock_current, $minStock);
            $item->whf_stock = $item->whfStock ? $item->whfStock->stock_current : 0;
            $item->total_produced = FinishGood::where('product_id', $item->id)->sum('quantity');
            $item->total_sold = WhfOutgoing::where('product_id', $item->id)->sum('quantity');
            $item->total_returned = ReturnModel::where('product_id', $item->id)->sum('quantity');
        }

        return [
            'data' => $data,
            'summary' => [
                'total_products' => $data->count(),
                'total_stock' => $data->sum('stock_current'),
                'total_whf_stock' => $data->sum(function ($item) {
                    return $item->whf_stock;
                }),
                'low_stock' => $data->filter(function ($item) use ($minStock) {
                    return $item->stock_current < $minStock && $item->stock_current > 0;
                })->count(),
                'empty_stock' => $data->filter(function ($item) {
                    return $item->stock_current <= 0;
                })->count(),
            ]
        ];
    }

    // ============================================
    // PRODUCTION REPORTS
    // ============================================

    public function getProductionReport(array $filters): array
    {
        $query = ProductionPlan::with(['product']);

        if (!empty($filters['start_date'])) {
            $query->whereDate('plan_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('plan_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['line_id'])) {
            $query->whereHas('distributions', function ($q) use ($filters) {
                $q->where('line_id', $filters['line_id']);
            });
        }

        $data = $query->orderBy('plan_date', 'desc')->get();

        $summary = [
            'total_plans' => $data->count(),
            'total_target' => $data->sum('quantity'),
            'total_produced' => 0,
            'total_remaining' => $data->sum('remaining_qty'),
            'achievement_percentage' => 0,
            'by_status' => [
                'draft' => $data->filter(fn($p) => $p->status === 'Draft')->count(),
                'proses' => $data->filter(fn($p) => $p->status === 'Proses')->count(),
                'selesai' => $data->filter(fn($p) => $p->status === 'Selesai')->count(),
                'batal' => $data->filter(fn($p) => $p->status === 'Batal')->count(),
            ]
        ];

        foreach ($data as $plan) {
            $plan->total_finished = FinishGood::where('plan_id', $plan->id)->sum('quantity');
            $plan->progress = $plan->quantity > 0 
                ? round(($plan->total_finished / $plan->quantity) * 100, 2) 
                : 0;
            
            $summary['total_produced'] += $plan->total_finished;
            
            // Get line details
            $plan->lines = $this->getPlanLineDetails($plan->id);
        }

        $summary['achievement_percentage'] = $summary['total_target'] > 0 
            ? round(($summary['total_produced'] / $summary['total_target']) * 100, 2)
            : 0;

        return [
            'data' => $data,
            'summary' => $summary
        ];
    }

    protected function getPlanLineDetails(int $planId): array
    {
        $distributions = PlanDistribution::with(['line'])
            ->where('plan_id', $planId)
            ->get();

        $lineDetails = [];
        foreach ($distributions as $dist) {
            $finishedQty = FinishGood::where('plan_id', $planId)
                ->where('line_id', $dist->line_id)
                ->sum('quantity');

            $lineDetails[] = [
                'line_name' => $dist->line->name ?? 'Unknown',
                'line_code' => $dist->line->code ?? '',
                'assigned' => $dist->quantity,
                'finished' => $finishedQty,
                'remaining' => $dist->quantity - $finishedQty,
                'progress' => $dist->quantity > 0 
                    ? round(($finishedQty / $dist->quantity) * 100, 2)
                    : 0,
                'status' => $finishedQty >= $dist->quantity ? 'Selesai' : 'Running'
            ];
        }

        return $lineDetails;
    }

    // ============================================
    // WAREHOUSE REPORTS (Material Flow)
    // ============================================

    public function getWarehouseReport(array $filters): array
    {
        $startDate = $filters['start_date'] ?? Carbon::now()->startOfMonth();
        $endDate = $filters['end_date'] ?? Carbon::now();

        // Material Incoming
        $incomingQuery = MaterialIncoming::with(['material', 'supplier']);
        if (!empty($filters['material_id'])) {
            $incomingQuery->where('material_id', $filters['material_id']);
        }
        $incomingQuery->whereBetween('incoming_date', [$startDate, $endDate]);
        $incoming = $incomingQuery->get();

        // Material Usage (from production plans)
        $usedQuery = ProductionPlan::whereIn('status', ['Proses', 'Selesai']);
        if (!empty($filters['material_id'])) {
            // This would require joining with formula details
        }
        $usedQuery->whereBetween('plan_date', [$startDate, $endDate]);
        $used = $usedQuery->get();

        // Other transactions (adjustments)
        $adjustments = OtherTransaction::with(['material']);
        if (!empty($filters['material_id'])) {
            $adjustments->where('material_id', $filters['material_id']);
        }
        $adjustments->whereBetween('transaction_date', [$startDate, $endDate]);
        $adjustments = $adjustments->get();

        // Current stock
        $stockQuery = Material::with(['supplier']);
        if (!empty($filters['material_id'])) {
            $stockQuery->where('id', $filters['material_id']);
        }
        $stock = $stockQuery->get();

        $summary = [
            'total_incoming' => $incoming->sum('quantity'),
            'total_used' => 0, // Will be calculated from formula
            'total_adjustment_in' => $adjustments->sum('quantity_in'),
            'total_adjustment_out' => $adjustments->sum('quantity_out'),
            'total_stock' => $stock->sum('stock_current'),
        ];

        // Calculate total used from plans
        foreach ($used as $plan) {
            // Get formula details to calculate material usage
            $formula = \App\Models\ProductFormula::where('product_id', $plan->product_id)->first();
            if ($formula) {
                foreach ($formula->details as $detail) {
                    if (empty($filters['material_id']) || $detail->material_id == $filters['material_id']) {
                        $summary['total_used'] += $detail->quantity * ($plan->quantity - $plan->remaining_qty);
                    }
                }
            }
        }

        return [
            'incoming' => $incoming,
            'used' => $used,
            'adjustments' => $adjustments,
            'stock' => $stock,
            'summary' => $summary
        ];
    }

    // ============================================
    // WHF REPORTS
    // ============================================

    public function getWhfReport(array $filters): array
    {
        $query = WhfStock::with(['product']);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'low') {
                $query->where('stock_current', '<', 100);
            } elseif ($filters['status'] === 'empty') {
                $query->where('stock_current', '<=', 0);
            }
        }

        $data = $query->orderBy('stock_current', 'desc')->get();

        // Add incoming and outgoing totals
        foreach ($data as $item) {
            $item->total_in = WhfIncoming::where('product_id', $item->product_id)->sum('quantity');
            $item->total_out = WhfOutgoing::where('product_id', $item->product_id)->sum('quantity');
            $item->status = $this->getWhfStockStatus($item->stock_current);
            
            // Get latest incoming
            $latestIncoming = WhfIncoming::where('product_id', $item->product_id)
                ->orderBy('incoming_date', 'desc')
                ->first();
            $item->last_incoming = $latestIncoming ? $latestIncoming->incoming_date : null;
            
            // Get latest outgoing
            $latestOutgoing = WhfOutgoing::where('product_id', $item->product_id)
                ->orderBy('outgoing_date', 'desc')
                ->first();
            $item->last_outgoing = $latestOutgoing ? $latestOutgoing->outgoing_date : null;
        }

        $summary = [
            'total_products' => $data->count(),
            'total_stock' => $data->sum('stock_current'),
            'total_incoming' => WhfIncoming::sum('quantity'),
            'total_outgoing' => WhfOutgoing::sum('quantity'),
            'empty_stock' => $data->filter(fn($item) => $item->stock_current <= 0)->count(),
            'low_stock' => $data->filter(fn($item) => $item->stock_current > 0 && $item->stock_current < 100)->count(),
        ];

        return [
            'data' => $data,
            'summary' => $summary
        ];
    }

    public function getWhfIncomingOutgoingReport(array $filters): array
    {
        // Incoming
        $incomingQuery = WhfIncoming::with(['product', 'finishGood']);
        if (!empty($filters['start_date'])) {
            $incomingQuery->whereDate('incoming_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $incomingQuery->whereDate('incoming_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['product_id'])) {
            $incomingQuery->where('product_id', $filters['product_id']);
        }
        $incoming = $incomingQuery->orderBy('incoming_date', 'desc')->get();

        // Outgoing
        $outgoingQuery = WhfOutgoing::with(['product', 'customer']);
        if (!empty($filters['start_date'])) {
            $outgoingQuery->whereDate('outgoing_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $outgoingQuery->whereDate('outgoing_date', '<=', $filters['end_date']);
        }
        if (!empty($filters['product_id'])) {
            $outgoingQuery->where('product_id', $filters['product_id']);
        }
        if (!empty($filters['customer_id'])) {
            $outgoingQuery->where('customer_id', $filters['customer_id']);
        }
        $outgoing = $outgoingQuery->orderBy('outgoing_date', 'desc')->get();

        $summary = [
            'total_incoming' => $incoming->sum('quantity'),
            'total_outgoing' => $outgoing->sum('quantity'),
            'total_products_incoming' => $incoming->pluck('product_id')->unique()->count(),
            'total_products_outgoing' => $outgoing->pluck('product_id')->unique()->count(),
            'total_customers' => $outgoing->pluck('customer_id')->unique()->count(),
        ];

        return [
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'summary' => $summary
        ];
    }

    // ============================================
    // EXPORT HELPERS
    // ============================================

    public function getExportData(string $type, array $filters): array
    {
        switch ($type) {
            case 'stock':
                return $this->getStockExportData($filters);
            case 'production':
                return $this->getProductionExportData($filters);
            case 'warehouse':
                return $this->getWarehouseExportData($filters);
            case 'whf':
                return $this->getWhfExportData($filters);
            default:
                return [];
        }
    }

    protected function getStockExportData(array $filters): array
    {
        $report = $this->getStockCardReport($filters);
        $data = [];
        $data[] = ['Tanggal', 'Kode Produk', 'Nama Produk', 'Stock Awal', 'Masuk', 'Keluar', 'Stock Akhir', 'Referensi'];
        
        foreach ($report['data'] as $item) {
            $data[] = [
                $item->transaction_date->format('Y-m-d'),
                $item->product_code,
                $item->product_name,
                $item->stock_before,
                $item->quantity_in,
                $item->quantity_out,
                $item->stock_after,
                $item->reference_number,
            ];
        }
        
        return $data;
    }

    protected function getProductionExportData(array $filters): array
    {
        $report = $this->getProductionReport($filters);
        $data = [];
        $data[] = ['No Plan', 'Tanggal', 'Produk', 'Target', 'Selesai', 'Sisa', 'Progress', 'Status'];
        
        foreach ($report['data'] as $item) {
            $data[] = [
                $item->plan_number,
                $item->plan_date->format('Y-m-d'),
                $item->product->name ?? 'N/A',
                $item->quantity,
                $item->total_finished ?? 0,
                $item->remaining_qty,
                $item->progress ?? 0 . '%',
                $item->status,
            ];
        }
        
        return $data;
    }

    protected function getWarehouseExportData(array $filters): array
    {
        $report = $this->getWarehouseReport($filters);
        $data = [];
        $data[] = ['Kode', 'Nama Material', 'Stock Awal', 'Masuk', 'Keluar', 'Stock Akhir'];
        
        foreach ($report['stock'] as $item) {
            $incoming = $report['incoming']->where('material_id', $item->id)->sum('quantity');
            $outgoing = $report['adjustments']->where('material_id', $item->id)->sum('quantity_out');
            $data[] = [
                $item->code,
                $item->name,
                $item->stock_initial,
                $incoming,
                $outgoing,
                $item->stock_current,
            ];
        }
        
        return $data;
    }

    protected function getWhfExportData(array $filters): array
    {
        $report = $this->getWhfReport($filters);
        $data = [];
        $data[] = ['Kode Produk', 'Nama Produk', 'Stock Awal', 'Total Masuk', 'Total Keluar', 'Stock Saat Ini', 'Status'];
        
        foreach ($report['data'] as $item) {
            $data[] = [
                $item->product->sku ?? 'N/A',
                $item->product->name ?? 'N/A',
                $item->stock_initial,
                $item->total_in,
                $item->total_out,
                $item->stock_current,
                $item->status['status'] ?? 'N/A',
            ];
        }
        
        return $data;
    }

    // ============================================
    // STATUS HELPERS
    // ============================================

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

    protected function getWhfStockStatus($current): array
    {
        if ($current <= 0) {
            return ['status' => 'Habis', 'class' => 'row-stock-danger', 'badge' => 'badge-danger'];
        }
        if ($current < 100) {
            return ['status' => 'Menipis', 'class' => 'row-stock-warning', 'badge' => 'badge-warning'];
        }
        if ($current < 500) {
            return ['status' => 'Sedang', 'class' => 'row-stock-info', 'badge' => 'badge-info'];
        }
        return ['status' => 'Aman', 'class' => '', 'badge' => 'badge-success'];
    }
}