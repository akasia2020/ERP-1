<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFormula;
use App\Models\FormulaDetail;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class FormulaService
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

    public function createFormula(int $productId, array $materials): ProductFormula
    {
        return DB::transaction(function () use ($productId, $materials) {
            // Check if product exists
            $product = Product::find($productId);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            // Delete existing formula if any
            $existing = ProductFormula::where('product_id', $productId)->first();
            if ($existing) {
                $existing->details()->delete();
                $existing->delete();
            }

            // Create new formula
            $formula = ProductFormula::create([
                'product_id' => $productId,
                'status' => 'Active',
                'created_by' => Auth::id(),
            ]);

            // Create formula details
            foreach ($materials as $material) {
                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'material_id' => $material['material_id'],
                    'quantity' => $material['quantity'],
                ]);
            }

            // Log activity
            $this->auditLogService->logWithUser(
                'Tambah',
                'Formula',
                "Formula untuk produk ID {$productId} dibuat"
            );

            return $formula;
        });
    }

    public function updateFormula(int $productId, array $materials): ProductFormula
    {
        return DB::transaction(function () use ($productId, $materials) {
            $formula = ProductFormula::where('product_id', $productId)->first();
            if (!$formula) {
                throw new Exception('Formula tidak ditemukan');
            }

            // Delete existing details
            $formula->details()->delete();

            // Create new details
            foreach ($materials as $material) {
                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'material_id' => $material['material_id'],
                    'quantity' => $material['quantity'],
                ]);
            }

            // Log activity
            $this->auditLogService->logWithUser(
                'Edit',
                'Formula',
                "Formula untuk produk ID {$productId} diperbarui"
            );

            return $formula;
        });
    }

    public function deleteFormula(int $productId): bool
    {
        return DB::transaction(function () use ($productId) {
            $formula = ProductFormula::where('product_id', $productId)->first();
            if ($formula) {
                $formula->details()->delete();
                $formula->delete();

                $this->auditLogService->logWithUser(
                    'Hapus',
                    'Formula',
                    "Formula untuk produk ID {$productId} dihapus"
                );
            }
            return true;
        });
    }

    public function getFormula(int $productId): ?ProductFormula
    {
        return ProductFormula::with(['details', 'details.material', 'details.material.supplier'])
            ->where('product_id', $productId)
            ->first();
    }

    public function calculateRequirements(int $productId, int $quantity): ?array
    {
        $formula = $this->getFormula($productId);
        if (!$formula) {
            return null;
        }

        $requirements = [];
        $totalMaterials = 0;

        foreach ($formula->details as $detail) {
            $needQty = $detail->quantity * $quantity;
            $material = $detail->material;
            $isAvailable = $material->stock_current >= $needQty;

            $requirements[] = [
                'material_id' => $material->id,
                'code' => $material->code,
                'name' => $material->name,
                'specification' => $material->specification,
                'unit' => $material->unit,
                'qty_per_unit' => $detail->quantity,
                'total_required' => $needQty,
                'stock_current' => $material->stock_current,
                'stock_minimum' => $material->stock_minimum,
                'is_available' => $isAvailable,
                'status' => $isAvailable ? 'Cukup' : 'Kurang'
            ];

            $totalMaterials++;
        }

        return [
            'total_materials' => $totalMaterials,
            'requirements' => $requirements,
        ];
    }

    public function validateStockForPlan(int $productId, int $quantity): array
    {
        $requirements = $this->calculateRequirements($productId, $quantity);
        if (!$requirements) {
            return ['valid' => false, 'message' => 'Produk belum memiliki formula'];
        }

        $insufficient = [];
        foreach ($requirements['requirements'] as $req) {
            if (!$req['is_available']) {
                $insufficient[] = $req['code'] . ' - ' . $req['name'] . 
                    ' (butuh: ' . $req['total_required'] . ', tersedia: ' . $req['stock_current'] . ')';
            }
        }

        if (count($insufficient) > 0) {
            return [
                'valid' => false,
                'message' => 'Stock bahan baku tidak mencukupi: ' . implode('; ', $insufficient),
                'insufficient' => $insufficient,
                'requirements' => $requirements
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock mencukupi',
            'requirements' => $requirements
        ];
    }
}