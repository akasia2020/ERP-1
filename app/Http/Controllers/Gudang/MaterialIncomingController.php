<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gudang\MaterialIncomingRequest;
use App\Models\Material;
use App\Models\MaterialIncoming;
use App\Models\Supplier;
use App\Models\StockCard;
use App\Services\StockService;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialIncomingController extends Controller
{
    protected StockService $stockService;
    protected AuditLogService $auditLogService;

    public function __construct(StockService $stockService, AuditLogService $auditLogService)
    {
        $this->stockService = $stockService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of material incoming.
     */
    public function index(Request $request)
    {
        $query = MaterialIncoming::with(['material', 'supplier']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('material_id')) {
            $query->byMaterial($request->material_id);
        }

        if ($request->filled('supplier_id')) {
            $query->bySupplier($request->supplier_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $incomings = $query->orderBy('created_at', 'desc')->paginate(15);
        $materials = Material::all();
        $suppliers = Supplier::active()->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $incomings->items(),
                'current_page' => $incomings->currentPage(),
                'last_page' => $incomings->lastPage(),
                'total' => $incomings->total(),
            ]);
        }

        return view('gudang.material-incoming', compact('incomings', 'materials', 'suppliers'));
    }

    /**
     * Store a newly created material incoming.
     */
    public function store(MaterialIncomingRequest $request)
    {
        try {
            DB::beginTransaction();

            $material = Material::lockForUpdate()->find($request->material_id);
            if (!$material) {
                throw new \Exception('Material tidak ditemukan');
            }

            $stockBefore = $material->stock_current;

            // Create incoming record
            $incoming = MaterialIncoming::create([
                'transaction_number' => MaterialIncoming::generateTransactionNumber(),
                'material_id' => $request->material_id,
                'quantity' => $request->quantity,
                'stock_before' => $stockBefore,
                'po_number' => $request->po_number,
                'supplier_id' => $request->supplier_id,
                'incoming_date' => $request->incoming_date ?? now(),
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Update stock
            $this->stockService->addMaterialStock(
                $request->material_id,
                $request->quantity,
                ['transaction_number' => $incoming->transaction_number]
            );

            // Create stock card
            $this->createStockCard($material, $request->quantity, $incoming->transaction_number);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Material Incoming',
                "Bahan masuk {$incoming->transaction_number} - {$material->code} +{$request->quantity} (sebelum: {$stockBefore}, sesudah: {$material->stock_current})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bahan masuk berhasil disimpan',
                'data' => $incoming
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan bahan masuk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified material incoming.
     */
    public function update(MaterialIncomingRequest $request, MaterialIncoming $materialIncoming)
    {
        try {
            DB::beginTransaction();

            $material = Material::lockForUpdate()->find($materialIncoming->material_id);
            if (!$material) {
                throw new \Exception('Material tidak ditemukan');
            }

            $oldQty = $materialIncoming->quantity;
            $newQty = $request->quantity;
            $diff = $newQty - $oldQty;

            // Update stock
            if ($diff > 0) {
                $this->stockService->addMaterialStock(
                    $material->id,
                    $diff,
                    ['transaction_number' => $materialIncoming->transaction_number]
                );
            } elseif ($diff < 0) {
                $this->stockService->subtractMaterialStock(
                    $material->id,
                    abs($diff),
                    ['transaction_number' => $materialIncoming->transaction_number]
                );
            }

            // Update incoming record
            $materialIncoming->update([
                'quantity' => $newQty,
                'po_number' => $request->po_number,
                'supplier_id' => $request->supplier_id,
                'incoming_date' => $request->incoming_date,
                'notes' => $request->notes,
            ]);

            $this->auditLogService->logWithUser(
                'Edit',
                'Material Incoming',
                "Bahan masuk {$materialIncoming->transaction_number} diubah (qty: {$oldQty} → {$newQty})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bahan masuk berhasil diperbarui',
                'data' => $materialIncoming
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui bahan masuk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified material incoming.
     */
    public function destroy(MaterialIncoming $materialIncoming)
    {
        try {
            DB::beginTransaction();

            // Reverse stock
            $this->stockService->subtractMaterialStock(
                $materialIncoming->material_id,
                $materialIncoming->quantity,
                ['transaction_number' => $materialIncoming->transaction_number]
            );

            $this->auditLogService->logWithUser(
                'Hapus',
                'Material Incoming',
                "Bahan masuk {$materialIncoming->transaction_number} dihapus (stock dikembalikan)"
            );

            $materialIncoming->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bahan masuk berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus bahan masuk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * IMPORT MATERIAL INCOMING FROM EXCEL
     * HANYA SATU METHOD import() - TIDAK ADA DUPLIKAT
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importMaterialIncoming($request->file('file'));

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
     * Export Material Incoming to Excel.
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
     * Search material incoming.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $incomings = MaterialIncoming::search($keyword)->limit(20)->get();
        return response()->json($incomings);
    }

    /**
     * Create stock card for material incoming.
     */
    protected function createStockCard(Material $material, int $quantity, string $transactionNumber): void
    {
        StockCard::create([
            'transaction_date' => now(),
            'transaction_number' => $transactionNumber,
            'reference_number' => $transactionNumber,
            'product_id' => $material->id,
            'product_code' => $material->code,
            'product_name' => $material->name,
            'stock_before' => $material->stock_current - $quantity,
            'quantity_in' => $quantity,
            'quantity_out' => 0,
            'total_in' => $quantity,
            'total_out' => 0,
            'stock_after' => $material->stock_current,
            'total_materials' => 1,
            'tolerance' => 0,
            'transaction_type' => 'material_incoming',
            'created_by' => auth()->id(),
        ]);
    }
}