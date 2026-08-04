<?php

namespace App\Services;

use App\Models\WhfStock;
use App\Models\WhfIncoming;
use App\Models\WhfOutgoing;
use App\Models\FinishGood;
use App\Models\Product;
use App\Models\Customer;
use App\Models\StockCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class WhfService
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

    public function receiveFinishGood(int $finishGoodId): WhfIncoming
    {
        return DB::transaction(function () use ($finishGoodId) {
            $finishGood = FinishGood::lockForUpdate()->find($finishGoodId);
            if (!$finishGood) {
                throw new Exception('Finish Good tidak ditemukan');
            }

            if ($finishGood->status !== 'Selesai') {
                throw new Exception('Finish Good belum selesai diproduksi');
            }

            // Check if already received
            $existing = WhfIncoming::where('finish_good_id', $finishGoodId)->first();
            if ($existing) {
                throw new Exception('Finish Good sudah diterima WHF');
            }

            $product = Product::find($finishGood->product_id);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            // Create WHF incoming record
            $incoming = WhfIncoming::create([
                'finish_good_id' => $finishGoodId,
                'product_id' => $finishGood->product_id,
                'quantity' => $finishGood->quantity,
                'incoming_date' => $finishGood->finish_date,
                'status' => 'Selesai',
                'created_by' => Auth::id(),
            ]);

            // Update WHF Stock
            $whfStock = WhfStock::lockForUpdate()->firstOrCreate(
                ['product_id' => $finishGood->product_id],
                [
                    'stock_initial' => 0,
                    'stock_current' => 0,
                    'total_in' => 0,
                    'total_out' => 0,
                    'box_count' => 0
                ]
            );

            $whfStock->stock_current += $finishGood->quantity;
            $whfStock->total_in += $finishGood->quantity;
            $whfStock->box_count = $whfStock->calculateBox($product->packaging_qty);
            $whfStock->save();

            // Log activity
            $this->auditLogService->logWithUser(
                'Tambah',
                'WHF Incoming',
                "Finish Good {$finishGood->finish_number} diterima WHF ({$finishGood->quantity} pcs)"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Produk Masuk WHF',
                "Finish Good {$finishGood->finish_number} - {$product->name} ({$finishGood->quantity} pcs) telah diterima WHF"
            );

            return $incoming;
        });
    }

    public function processOutgoing(array $data): WhfOutgoing
    {
        return DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->find($data['product_id']);
            if (!$product) {
                throw new Exception('Produk tidak ditemukan');
            }

            // Check WHF stock
            $whfStock = WhfStock::lockForUpdate()->where('product_id', $data['product_id'])->first();
            if (!$whfStock || $whfStock->stock_current < $data['quantity']) {
                $available = $whfStock ? $whfStock->stock_current : 0;
                throw new Exception("Stock WHF tidak mencukupi (tersedia: {$available}, dibutuhkan: {$data['quantity']})");
            }

            // Validate customer if provided
            if (isset($data['customer_id']) && $data['customer_id']) {
                $customer = Customer::find($data['customer_id']);
                if (!$customer) {
                    throw new Exception('Customer tidak ditemukan');
                }
            }

            // Generate outgoing number
            $outgoingNumber = WhfOutgoing::generateOutgoingNumber();

            // Create outgoing record
            $outgoing = WhfOutgoing::create([
                'outgoing_number' => $outgoingNumber,
                'delivery_number' => $data['delivery_number'],
                'product_id' => $data['product_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'quantity' => $data['quantity'],
                'outgoing_date' => $data['outgoing_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Subtract product stock (this also updates WHF stock via StockService)
            $this->stockService->subtractProductStock(
                $data['product_id'],
                $data['quantity'],
                [
                    'outgoing_number' => $outgoingNumber,
                    'delivery_number' => $data['delivery_number']
                ]
            );

            // Create stock card
            $this->createStockCard($product, $data['quantity'], $outgoingNumber, $data['delivery_number']);

            // Log activity
            $this->auditLogService->logWithUser(
                'Tambah',
                'WHF Outgoing',
                "Produk keluar {$outgoingNumber} - {$product->sku} ({$data['quantity']} pcs) ke " . ($customer->name ?? 'Unknown')
            );

            // Create timeline
            app(AuthService::class)->addTimeline(
                Auth::id(),
                'info',
                'Produk keluar dari WHF',
                "{$outgoingNumber} - {$product->name} ({$data['quantity']} pcs)"
            );

            // Create notification
            $this->notificationService->createForAll(
                'Produk Keluar WHF',
                "Produk {$product->name} ({$data['quantity']} pcs) telah keluar dari WHF dengan SJ {$data['delivery_number']}"
            );

            return $outgoing;
        });
    }

    public function getWhfStock(int $productId): ?WhfStock
    {
        return WhfStock::with(['product'])->where('product_id', $productId)->first();
    }

    public function getAllWhfStocks()
    {
        return WhfStock::with(['product'])->get();
    }

    public function getIncomingHistory($productId = null, $startDate = null, $endDate = null)
    {
        $query = WhfIncoming::with(['product', 'finishGood', 'creator']);

        if ($productId) {
            $query->byProduct($productId);
        }

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->orderBy('incoming_date', 'desc')->get();
    }

    public function getOutgoingHistory($productId = null, $customerId = null, $startDate = null, $endDate = null)
    {
        $query = WhfOutgoing::with(['product', 'customer', 'creator']);

        if ($productId) {
            $query->byProduct($productId);
        }

        if ($customerId) {
            $query->byCustomer($customerId);
        }

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->orderBy('outgoing_date', 'desc')->get();
    }

    protected function createStockCard(Product $product, int $quantity, string $outgoingNumber, string $deliveryNumber): void
    {
        StockCard::create([
            'transaction_date' => now(),
            'transaction_number' => $outgoingNumber,
            'reference_number' => $deliveryNumber,
            'product_id' => $product->id,
            'product_code' => $product->sku,
            'product_name' => $product->name,
            'stock_before' => $product->stock_current + $quantity,
            'quantity_in' => 0,
            'quantity_out' => $quantity,
            'stock_after' => $product->stock_current,
            'total_materials' => 0,
            'tolerance' => 0,
            'transaction_type' => 'whf_outgoing',
            'notes' => "WHF Outgoing: {$outgoingNumber}, SJ: {$deliveryNumber}",
            'created_by' => Auth::id(),
        ]);
    }

    public function getWhfSummary(): array
    {
        $totalStock = WhfStock::sum('stock_current');
        $totalProducts = WhfStock::where('stock_current', '>', 0)->count();
        $totalIncoming = WhfIncoming::sum('quantity');
        $totalOutgoing = WhfOutgoing::sum('quantity');

        return [
            'total_stock' => $totalStock,
            'total_products' => $totalProducts,
            'total_incoming' => $totalIncoming,
            'total_outgoing' => $totalOutgoing,
        ];
    }

    public function getLowStockProducts(int $threshold = 100)
    {
        return WhfStock::with(['product'])
            ->where('stock_current', '<', $threshold)
            ->where('stock_current', '>', 0)
            ->get();
    }

    public function getEmptyStockProducts()
    {
        return WhfStock::with(['product'])
            ->where('stock_current', '<=', 0)
            ->get();
    }
}