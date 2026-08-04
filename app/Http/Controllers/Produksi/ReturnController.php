<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produksi\ReturnRequest;
use App\Models\Return as ReturnModel;
use App\Models\Product;
use App\Models\StockCard;
use App\Services\StockService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
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

    public function index(Request $request)
    {
        $query = ReturnModel::with(['product']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('store_name')) {
            $query->byStore($request->store_name);
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate(15);
        $products = Product::all();

        if ($request->ajax()) {
            return response()->json($returns);
        }

        return view('produksi.returns', compact('returns', 'products'));
    }

    public function store(ReturnRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = Product::lockForUpdate()->find($request->product_id);
            if (!$product) {
                throw new \Exception('Produk tidak ditemukan');
            }

            // Check stock availability
            if ($product->stock_current < $request->quantity) {
                throw new \Exception("Stock produk {$product->sku} tidak mencukupi (tersedia: {$product->stock_current}, dibutuhkan: {$request->quantity})");
            }

            // Create return record
            $return = ReturnModel::create([
                'delivery_number' => $request->delivery_number,
                'product_id' => $request->product_id,
                'store_name' => $request->store_name,
                'quantity' => $request->quantity,
                'return_date' => $request->return_date ?? now(),
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Subtract product stock
            $this->stockService->subtractProductStock(
                $request->product_id,
                $request->quantity,
                ['delivery_number' => $request->delivery_number]
            );

            // Create stock card
            $this->createStockCard($product, $request->quantity, $request->delivery_number);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Return',
                "Retur {$request->delivery_number} - {$product->sku} ({$request->quantity} pcs) dari {$request->store_name}"
            );

            $this->notificationService->createForAll(
                'Retur Barang',
                "Retur {$return->delivery_number} - {$product->name} ({$request->quantity} pcs) dari {$request->store_name}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retur berhasil disimpan',
                'data' => $return
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan retur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(ReturnRequest $request, ReturnModel $return)
    {
        try {
            DB::beginTransaction();

            $oldQty = $return->quantity;
            $newQty = $request->quantity;
            $diff = $newQty - $oldQty;

            $product = Product::lockForUpdate()->find($return->product_id);
            if (!$product) {
                throw new \Exception('Produk tidak ditemukan');
            }

            // Reverse old stock
            $this->stockService->addProductStock(
                $return->product_id,
                $oldQty,
                ['return_rollback' => $return->delivery_number]
            );

            // Apply new stock
            if ($product->stock_current < $newQty) {
                throw new \Exception("Stock produk {$product->sku} tidak mencukupi (tersedia: {$product->stock_current}, dibutuhkan: {$newQty})");
            }

            $this->stockService->subtractProductStock(
                $return->product_id,
                $newQty,
                ['delivery_number' => $request->delivery_number]
            );

            // Update return
            $return->update([
                'delivery_number' => $request->delivery_number,
                'store_name' => $request->store_name,
                'quantity' => $newQty,
                'return_date' => $request->return_date,
                'notes' => $request->notes,
            ]);

            $this->auditLogService->logWithUser(
                'Edit',
                'Return',
                "Retur {$return->delivery_number} diperbarui (qty: {$oldQty} → {$newQty})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retur berhasil diperbarui',
                'data' => $return
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui retur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(ReturnModel $return)
    {
        try {
            DB::beginTransaction();

            // Reverse stock
            $this->stockService->addProductStock(
                $return->product_id,
                $return->quantity,
                ['return_delete' => $return->delivery_number]
            );

            $this->auditLogService->logWithUser(
                'Hapus',
                'Return',
                "Retur {$return->delivery_number} dihapus"
            );

            $return->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Retur berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus retur: ' . $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $returns = ReturnModel::search($keyword)->limit(20)->get();
        return response()->json($returns);
    }

    /**
     * Import returns from Excel file
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:20480'
            ]);

            $result = app(\App\Services\ImportService::class)->importReturns($request->file('file'));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'errors' => $result['errors'] ?? []
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Export functionality coming soon'
        ], 501);
    }

    protected function createStockCard($product, $quantity, $deliveryNumber): void
    {
        StockCard::create([
            'transaction_date' => now(),
            'transaction_number' => $deliveryNumber,
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
            'transaction_type' => 'return',
            'notes' => "Retur dari toko: {$deliveryNumber}",
            'created_by' => auth()->id(),
        ]);
    }
}