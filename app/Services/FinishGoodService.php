<?php

namespace App\Services;

use App\Models\FinishGood;
use App\Models\ProductionPlan;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\WhfIncoming;
use App\Models\WhfStock;
use App\Models\StockCard;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class FinishGoodService
{
    protected StockService $stockService;
    protected AuditLogService $auditLogService;
    protected NotificationService $notificationService;

    public function __construct(
        StockService $stockService,
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ) {
        $this->stockService = $stockService;
        $this->auditLogService = $auditLogService;
        $this->notificationService = $notificationService;
    }

    public function createFinishGood(array $data): FinishGood
    {
        return DB::transaction(function () use ($data) {
            // Lock and validate plan
            $plan = ProductionPlan::lockForUpdate()->find($data['plan_id']);
            if (!$plan) {
                throw new Exception('Planning tidak ditemukan');
            }

            if ($data['quantity'] > $plan->remaining_qty) {
                throw new Exception('Qty melebihi sisa planning (' . $plan->remaining_qty . ' pcs)');
            }

            // Validate product
            $product = Product::find($plan->product_id);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            // Validate line if provided
            $line = null;
            if (isset($data['line_id']) && $data['line_id']) {
                $line = ProductionLine::find($data['line_id']);
                if (!$line) {
                    throw new Exception('Line produksi tidak ditemukan');
                }
            }

            // Generate finish number
            $finishNumber = FinishGood::generateFinishNumber();

            // Create finish good record
            $finishGood = FinishGood::create([
                'plan_id' => $data['plan_id'],
                'product_id' => $plan->product_id,
                'line_id' => $data['line_id'] ?? null,
                'delivery_number' => $data['delivery_number'],
                'finish_number' => $finishNumber,
                'pic' => $data['pic'] ?? null,
                'quantity' => $data['quantity'],
                'qc_status' => $data['qc_status'] ?? FinishGood::QC_PASSED,
                'finish_date' => $data['finish_date'],
                'status' => FinishGood::STATUS_SELESAI,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Update plan remaining quantity
            $plan->remaining_qty -= $data['quantity'];
            $plan->status = $plan->remaining_qty > 0 
                ? ProductionPlan::STATUS_PROSES 
                : ProductionPlan::STATUS_SELESAI;
            $plan->save();

            // Add product stock
            $this->stockService->addProductStock(
                $plan->product_id,
                $data['quantity'],
                [
                    'finish_number' => $finishNumber,
                    'delivery_number' => $data['delivery_number'],
                    'plan_number' => $plan->plan_number
                ]
            );

            // Create WHF incoming record
            $whfIncoming = WhfIncoming::create([
                'finish_good_id' => $finishGood->id,
                'product_id' => $plan->product_id,
                'quantity' => $data['quantity'],
                'incoming_date' => $data['finish_date'],
                'status' => 'Selesai',
                'created_by' => Auth::id(),
            ]);

            // Create stock card
            $this->createStockCard($product, $data['quantity'], $finishNumber, $data['delivery_number'], $plan->plan_number);

            // Log activity
            $this->auditLogService->logWithUser(
                'Tambah',
                'Finish Good',
                "Finish Good {$finishNumber} - {$product->name} ({$data['quantity']} pcs) dikirim ke WHF"
            );

            // Create timeline
            app(AuthService::class)->addTimeline(
                Auth::id(),
                'success',
                'Finish Good dikirim ke WHF',
                "{$finishNumber} - {$data['quantity']} pcs"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Finish Good Dikirim ke WHF',
                "Finish Good {$finishNumber} - {$product->name} ({$data['quantity']} pcs) telah dikirim ke WHF"
            );

            return $finishGood;
        });
    }

    public function getFinishGood($id): ?FinishGood
    {
        return FinishGood::with(['plan', 'product', 'line', 'creator'])->find($id);
    }

    public function getFinishGoodsByPlan($planId)
    {
        return FinishGood::with(['product', 'line'])
            ->byPlan($planId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getFinishGoodsByProduct($productId)
    {
        return FinishGood::with(['plan', 'line'])
            ->byProduct($productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getFinishGoodsByDateRange($startDate, $endDate)
    {
        return FinishGood::with(['product', 'plan'])
            ->byDateRange($startDate, $endDate)
            ->orderBy('finish_date', 'desc')
            ->get();
    }

    protected function createStockCard($product, $quantity, $finishNumber, $deliveryNumber, $planNumber): void
    {
        $product = Product::find($product->id);
        if (!$product) return;

        StockCard::create([
            'transaction_date' => now(),
            'transaction_number' => $finishNumber,
            'reference_number' => $planNumber,
            'product_id' => $product->id,
            'product_code' => $product->sku,
            'product_name' => $product->name,
            'stock_before' => $product->stock_current - $quantity,
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'stock_after' => $product->stock_current,
            'total_materials' => 0,
            'tolerance' => 0,
            'transaction_type' => 'finish_good',
            'notes' => "Finish Good: {$finishNumber}, SJ: {$deliveryNumber}",
            'created_by' => Auth::id(),
        ]);
    }

    public function validateFinishNumber($number): bool
    {
        return !FinishGood::where('finish_number', $number)->exists();
    }

    public function generateFinishNumber(): string
    {
        return FinishGood::generateFinishNumber();
    }
}