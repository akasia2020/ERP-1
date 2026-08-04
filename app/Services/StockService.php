<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\WhfStock;
use App\Models\StockCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class StockService
{
    protected AuditLogService $auditLogService;
    protected NotificationService $notificationService;

    public function __construct(
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ) {
        $this->auditLogService = $auditLogService;
        $this->notificationService = $notificationService;
    }

    public function addMaterialStock(int $materialId, int $quantity, ?array $reference = null): Material
    {
        return DB::transaction(function () use ($materialId, $quantity, $reference) {
            $material = Material::lockForUpdate()->find($materialId);
            if (!$material) {
                throw new Exception('Material tidak ditemukan');
            }

            $stockBefore = $material->stock_current;
            $material->stock_current += $quantity;
            $material->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Stock Update',
                'Material',
                "Stock material {$material->code} +{$quantity} (sebelum: {$stockBefore}, sesudah: {$material->stock_current})"
            );

            return $material;
        });
    }

    public function subtractMaterialStock(int $materialId, int $quantity, ?array $reference = null): Material
    {
        return DB::transaction(function () use ($materialId, $quantity, $reference) {
            $material = Material::lockForUpdate()->find($materialId);
            if (!$material) {
                throw new Exception('Material tidak ditemukan');
            }

            if ($material->stock_current < $quantity) {
                throw new Exception("Stock material {$material->code} tidak mencukupi (tersedia: {$material->stock_current}, dibutuhkan: {$quantity})");
            }

            $stockBefore = $material->stock_current;
            $material->stock_current -= $quantity;
            $material->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Stock Update',
                'Material',
                "Stock material {$material->code} -{$quantity} (sebelum: {$stockBefore}, sesudah: {$material->stock_current})"
            );

            return $material;
        });
    }

    public function addProductStock(int $productId, int $quantity, ?array $reference = null): Product
    {
        return DB::transaction(function () use ($productId, $quantity, $reference) {
            $product = Product::lockForUpdate()->find($productId);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            $stockBefore = $product->stock_current;
            $product->stock_current += $quantity;
            $product->save();

            // Update WHF Stock
            $whfStock = WhfStock::lockForUpdate()->firstOrCreate(
                ['product_id' => $productId],
                ['stock_initial' => 0, 'stock_current' => 0, 'total_in' => 0, 'total_out' => 0, 'box_count' => 0]
            );

            $whfStock->stock_current += $quantity;
            $whfStock->total_in += $quantity;
            $whfStock->box_count = $this->calculateBox($whfStock->stock_current, $product->packaging_qty);
            $whfStock->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Stock Update',
                'Product',
                "Stock produk {$product->sku} +{$quantity} (sebelum: {$stockBefore}, sesudah: {$product->stock_current})"
            );

            // Create stock card
            $this->createStockCard($productId, $quantity, 0, $reference);

            return $product;
        });
    }

    public function subtractProductStock(int $productId, int $quantity, ?array $reference = null): Product
    {
        return DB::transaction(function () use ($productId, $quantity, $reference) {
            $product = Product::lockForUpdate()->find($productId);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            if ($product->stock_current < $quantity) {
                throw new Exception("Stock produk {$product->sku} tidak mencukupi (tersedia: {$product->stock_current}, dibutuhkan: {$quantity})");
            }

            $stockBefore = $product->stock_current;
            $product->stock_current -= $quantity;
            $product->save();

            // Update WHF Stock
            $whfStock = WhfStock::lockForUpdate()->where('product_id', $productId)->first();
            if ($whfStock) {
                if ($whfStock->stock_current < $quantity) {
                    throw new Exception("Stock WHF untuk {$product->sku} tidak mencukupi");
                }
                $whfStock->stock_current -= $quantity;
                $whfStock->total_out += $quantity;
                $whfStock->box_count = $this->calculateBox($whfStock->stock_current, $product->packaging_qty);
                $whfStock->save();
            }

            // Log activity
            $this->auditLogService->logWithUser(
                'Stock Update',
                'Product',
                "Stock produk {$product->sku} -{$quantity} (sebelum: {$stockBefore}, sesudah: {$product->stock_current})"
            );

            // Create stock card
            $this->createStockCard($productId, 0, $quantity, $reference);

            return $product;
        });
    }

    protected function calculateBox(int $stock, int $packagingQty): int
    {
        if ($packagingQty <= 0) return 0;
        return floor($stock / $packagingQty);
    }

    protected function createStockCard(int $productId, int $qtyIn, int $qtyOut, ?array $reference = null): void
    {
        $product = Product::find($productId);
        if (!$product) return;

        StockCard::create([
            'product_id' => $productId,
            'stock_before' => $product->stock_current - $qtyIn + $qtyOut,
            'quantity_in' => $qtyIn,
            'quantity_out' => $qtyOut,
            'stock_after' => $product->stock_current,
            'reference' => $reference ? json_encode($reference) : null
        ]);
    }
}