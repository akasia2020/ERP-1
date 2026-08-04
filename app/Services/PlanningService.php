<?php

namespace App\Services;

use App\Models\ProductionPlan;
use App\Models\PlanDistribution;
use App\Models\Product;
use App\Models\Material;
use App\Models\StockCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PlanningService
{
    protected StockService $stockService;
    protected FormulaService $formulaService;
    protected AuditLogService $auditLogService;
    protected NotificationService $notificationService;

    public function __construct(
        StockService $stockService,
        FormulaService $formulaService,
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ) {
        $this->stockService = $stockService;
        $this->formulaService = $formulaService;
        $this->auditLogService = $auditLogService;
        $this->notificationService = $notificationService;
    }

    public function createPlan(array $data): ProductionPlan
    {
        return DB::transaction(function () use ($data) {
            $product = Product::find($data['product_id']);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            // Validate stock availability
            $validation = $this->formulaService->validateStockForPlan(
                $data['product_id'],
                $data['quantity']
            );

            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Generate plan number
            $planNumber = ProductionPlan::generatePlanNumber();

            // Create plan
            $plan = ProductionPlan::create([
                'plan_number' => $planNumber,
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'remaining_qty' => $data['quantity'],
                'total_materials' => $validation['requirements']['total_materials'] ?? 0,
                'status' => ProductionPlan::STATUS_DRAFT,
                'priority' => $data['priority'] ?? ProductionPlan::PRIORITY_MEDIUM,
                'plan_date' => $data['plan_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Log activity
            $this->auditLogService->logWithUser(
                'Tambah',
                'Planning',
                "Planning {$planNumber} dibuat untuk produk {$product->name} (Qty: {$data['quantity']})"
            );

            // Create timeline
            app(AuthService::class)->addTimeline(
                Auth::id(),
                'info',
                'Planning produksi dibuat',
                "{$planNumber} - " . $data['quantity'] . " pcs"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Planning Produksi Baru',
                "Planning {$planNumber} telah dibuat untuk produk {$product->name}"
            );

            return $plan;
        });
    }

    public function sendToProduction(int $planId, array $distributions): ProductionPlan
    {
        return DB::transaction(function () use ($planId, $distributions) {
            $plan = ProductionPlan::lockForUpdate()->find($planId);
            if (!$plan) {
                throw new Exception('Planning tidak ditemukan');
            }

            if (!$plan->isDraft()) {
                throw new Exception('Planning sudah diproses');
            }

            // Calculate total distributed
            $totalDistributed = 0;
            foreach ($distributions as $dist) {
                $totalDistributed += $dist['quantity'];
            }

            if ($totalDistributed > $plan->quantity) {
                throw new Exception('Total distribusi melebihi qty planning');
            }

            // Calculate material requirements for distributed qty
            $requirements = $this->formulaService->calculateRequirements(
                $plan->product_id,
                $totalDistributed
            );

            if ($requirements) {
                foreach ($requirements['requirements'] as $req) {
                    // Subtract material stock
                    $this->stockService->subtractMaterialStock(
                        $req['material_id'],
                        $req['total_required'],
                        ['plan_number' => $plan->plan_number]
                    );

                    // Create stock card for material
                    $this->createMaterialStockCard(
                        $req['material_id'],
                        $req['total_required'],
                        0,
                        $plan->plan_number,
                        'production_plan'
                    );
                }
            }

            // Create distributions
            foreach ($distributions as $dist) {
                PlanDistribution::create([
                    'plan_id' => $planId,
                    'line_id' => $dist['line_id'],
                    'quantity' => $dist['quantity'],
                    'distribution_date' => $dist['distribution_date'] ?? now(),
                ]);
            }

            // Update plan
            $plan->remaining_qty = $plan->quantity - $totalDistributed;
            $plan->status = $plan->remaining_qty > 0 
                ? ProductionPlan::STATUS_PROSES 
                : ProductionPlan::STATUS_SELESAI;
            $plan->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Kirim',
                'Planning',
                "Planning {$plan->plan_number} dikirim ke produksi ({$totalDistributed} pcs)"
            );

            // Create timeline
            app(AuthService::class)->addTimeline(
                Auth::id(),
                'success',
                'Planning dikirim ke produksi',
                "{$plan->plan_number} - {$totalDistributed} pcs"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Planning Dikirim ke Produksi',
                "Planning {$plan->plan_number} dikirim ke Produksi ({$totalDistributed} pcs)"
            );

            return $plan;
        });
    }

    public function continuePlan(int $planId, array $distributions): ProductionPlan
    {
        return DB::transaction(function () use ($planId, $distributions) {
            $plan = ProductionPlan::lockForUpdate()->find($planId);
            if (!$plan) {
                throw new Exception('Planning tidak ditemukan');
            }

            if ($plan->remaining_qty <= 0) {
                throw new Exception('Tidak ada sisa planning');
            }

            // Calculate total distributed
            $totalDistributed = 0;
            foreach ($distributions as $dist) {
                $totalDistributed += $dist['quantity'];
            }

            if ($totalDistributed > $plan->remaining_qty) {
                throw new Exception('Total distribusi melebihi sisa planning');
            }

            // Calculate material requirements for distributed qty
            $requirements = $this->formulaService->calculateRequirements(
                $plan->product_id,
                $totalDistributed
            );

            if ($requirements) {
                foreach ($requirements['requirements'] as $req) {
                    $this->stockService->subtractMaterialStock(
                        $req['material_id'],
                        $req['total_required'],
                        ['plan_number' => $plan->plan_number]
                    );

                    $this->createMaterialStockCard(
                        $req['material_id'],
                        $req['total_required'],
                        0,
                        $plan->plan_number,
                        'production_plan'
                    );
                }
            }

            // Create distributions
            foreach ($distributions as $dist) {
                PlanDistribution::create([
                    'plan_id' => $planId,
                    'line_id' => $dist['line_id'],
                    'quantity' => $dist['quantity'],
                    'distribution_date' => $dist['distribution_date'] ?? now(),
                ]);
            }

            // Update plan
            $plan->remaining_qty -= $totalDistributed;
            $plan->status = $plan->remaining_qty > 0 
                ? ProductionPlan::STATUS_PROSES 
                : ProductionPlan::STATUS_SELESAI;
            $plan->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Lanjutkan',
                'Planning',
                "Planning {$plan->plan_number} dilanjutkan ({$totalDistributed} pcs)"
            );

            // Create timeline
            app(AuthService::class)->addTimeline(
                Auth::id(),
                'info',
                'Planning dilanjutkan',
                "{$plan->plan_number} - {$totalDistributed} pcs"
            );

            return $plan;
        });
    }

    public function cancelPlan(int $planId): ProductionPlan
    {
        return DB::transaction(function () use ($planId) {
            $plan = ProductionPlan::lockForUpdate()->find($planId);
            if (!$plan) {
                throw new Exception('Planning tidak ditemukan');
            }

            if ($plan->isSelesai() || $plan->isBatal()) {
                throw new Exception('Planning sudah selesai atau dibatalkan');
            }

            $plan->status = ProductionPlan::STATUS_BATAL;
            $plan->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Batal',
                'Planning',
                "Planning {$plan->plan_number} dibatalkan"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Planning Dibatalkan',
                "Planning {$plan->plan_number} telah dibatalkan"
            );

            return $plan;
        });
    }

    public function getPlanDistributions(int $planId): array
    {
        $plan = ProductionPlan::with(['distributions', 'distributions.line'])->find($planId);
        if (!$plan) {
            return [];
        }

        return $plan->distributions->map(function ($dist) {
            return [
                'line_id' => $dist->line_id,
                'line_code' => $dist->line->code ?? '',
                'line_name' => $dist->line->name ?? '',
                'quantity' => $dist->quantity,
                'distribution_date' => $dist->distribution_date,
            ];
        })->toArray();
    }

    public function calculateMaterials(int $productId, int $quantity): array
    {
        return $this->formulaService->calculateRequirements($productId, $quantity) ?? [];
    }

    protected function createMaterialStockCard(
        int $materialId,
        int $quantityIn,
        int $quantityOut,
        string $referenceNumber,
        string $transactionType
    ): void {
        $material = Material::find($materialId);
        if (!$material) return;

        StockCard::create([
            'transaction_date' => now(),
            'transaction_number' => $referenceNumber,
            'reference_number' => $referenceNumber,
            'product_id' => $materialId,
            'product_code' => $material->code,
            'product_name' => $material->name,
            'stock_before' => $material->stock_current - $quantityIn + $quantityOut,
            'quantity_in' => $quantityIn,
            'quantity_out' => $quantityOut,
            'stock_after' => $material->stock_current,
            'transaction_type' => $transactionType,
            'created_by' => Auth::id(),
        ]);
    }
}