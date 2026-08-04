<?php

namespace App\Http\Controllers\Whf;

use App\Http\Controllers\Controller;
use App\Http\Requests\Whf\ProductOutgoingRequest;
use App\Models\WhfOutgoing;
use App\Models\Product;
use App\Models\Customer;
use App\Models\WhfStock;
use App\Services\WhfService;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductOutgoingController extends Controller
{
    protected WhfService $whfService;
    protected AuditLogService $auditLogService;

    public function __construct(
        WhfService $whfService,
        AuditLogService $auditLogService
    ) {
        $this->whfService = $whfService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of product outgoing.
     */
    public function index(Request $request)
    {
        $query = WhfOutgoing::with(['product', 'customer', 'creator']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $outgoings = $query->orderBy('created_at', 'desc')->paginate(15);
        $products = Product::all();
        $customers = Customer::all();

        if ($request->ajax()) {
            return response()->json([
                'data' => $outgoings->items(),
                'current_page' => $outgoings->currentPage(),
                'last_page' => $outgoings->lastPage(),
                'total' => $outgoings->total(),
            ]);
        }

        return view('whf.product-outgoing', compact('outgoings', 'products', 'customers'));
    }

    /**
     * Store a newly created product outgoing.
     */
    public function store(ProductOutgoingRequest $request)
    {
        try {
            $outgoing = $this->whfService->processOutgoing($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dikeluarkan dari WHF',
                'data' => $outgoing
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengeluarkan produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product outgoing.
     */
    public function update(ProductOutgoingRequest $request, WhfOutgoing $whfOutgoing)
    {
        try {
            DB::beginTransaction();

            $oldQty = $whfOutgoing->quantity;
            $newQty = $request->quantity;
            $diff = $newQty - $oldQty;

            $product = Product::lockForUpdate()->find($whfOutgoing->product_id);
            if (!$product) {
                throw new \Exception('Produk tidak ditemukan');
            }

            // Reverse old stock
            $whfStock = WhfStock::lockForUpdate()->where('product_id', $whfOutgoing->product_id)->first();
            if ($whfStock) {
                $whfStock->stock_current += $oldQty;
                $whfStock->total_out -= $oldQty;
                $whfStock->save();

                $product->stock_current += $oldQty;
                $product->save();
            }

            // Apply new stock
            if ($whfStock && $whfStock->stock_current < $newQty) {
                throw new \Exception("Stock WHF tidak mencukupi untuk penambahan qty");
            }

            if ($whfStock) {
                $whfStock->stock_current -= $newQty;
                $whfStock->total_out += $newQty;
                $whfStock->save();

                $product->stock_current -= $newQty;
                $product->save();
            }

            // Update outgoing
            $whfOutgoing->update([
                'delivery_number' => $request->delivery_number,
                'customer_id' => $request->customer_id,
                'quantity' => $newQty,
                'outgoing_date' => $request->outgoing_date,
                'notes' => $request->notes,
            ]);

            $this->auditLogService->logWithUser(
                'Edit',
                'WHF Outgoing',
                "Produk keluar {$whfOutgoing->outgoing_number} diperbarui (qty: {$oldQty} → {$newQty})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk keluar berhasil diperbarui',
                'data' => $whfOutgoing
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk keluar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product outgoing.
     */
    public function destroy(WhfOutgoing $whfOutgoing)
    {
        try {
            DB::beginTransaction();

            // Reverse stock
            $whfStock = WhfStock::lockForUpdate()->where('product_id', $whfOutgoing->product_id)->first();
            if ($whfStock) {
                $whfStock->stock_current += $whfOutgoing->quantity;
                $whfStock->total_out -= $whfOutgoing->quantity;
                $whfStock->save();

                $product = Product::find($whfOutgoing->product_id);
                if ($product) {
                    $product->stock_current += $whfOutgoing->quantity;
                    $product->save();
                }
            }

            $this->auditLogService->logWithUser(
                'Hapus',
                'WHF Outgoing',
                "Produk keluar {$whfOutgoing->outgoing_number} dihapus"
            );

            $whfOutgoing->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk keluar berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk keluar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * IMPORT PRODUCT OUTGOING FROM EXCEL
     * HANYA SATU METHOD import() - TIDAK ADA DUPLIKAT
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importWhfOutgoing($request->file('file'));

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
    }

    /**
     * Export Product Outgoing to Excel.
     */
    public function export(Request $request)
    {
        // Export functionality - akan diimplementasikan di Phase 7
        return response()->json([
            'success' => false,
            'message' => 'Export functionality coming soon'
        ], 501);
    }

    /**
     * Search product outgoing.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $outgoings = WhfOutgoing::search($keyword)->limit(20)->get();
        return response()->json($outgoings);
    }

    /**
     * Generate outgoing number.
     */
    public function generateNumber(Request $request)
    {
        $number = WhfOutgoing::generateOutgoingNumber();
        return response()->json(['outgoing_number' => $number]);
    }

    /**
     * Validate stock before outgoing.
     */
    public function validateStock(Product $product, $qty)
    {
        $whfStock = WhfStock::where('product_id', $product->id)->first();
        $available = $whfStock ? $whfStock->stock_current : 0;
        $valid = $available >= $qty && $qty > 0;

        return response()->json([
            'valid' => $valid,
            'message' => $valid ? 'Stock tersedia' : "Stock tidak mencukupi (tersedia: {$available})",
            'available' => $available
        ]);
    }
}