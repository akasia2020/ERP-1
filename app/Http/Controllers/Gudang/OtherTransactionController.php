<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gudang\OtherTransactionRequest;
use App\Models\OtherTransaction;
use App\Models\Material;
use App\Services\StockService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OtherTransactionController extends Controller
{
    protected StockService $stockService;
    protected AuditLogService $auditLogService;

    public function __construct(StockService $stockService, AuditLogService $auditLogService)
    {
        $this->stockService = $stockService;
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $query = OtherTransaction::with(['material']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('material_id')) {
            $query->byMaterial($request->material_id);
        }

        if ($request->filled('need_type')) {
            $query->byNeedType($request->need_type);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(15);
        $materials = Material::all();

        if ($request->ajax()) {
            return response()->json($transactions);
        }

        return view('gudang.other-transactions', compact('transactions', 'materials'));
    }

    public function store(OtherTransactionRequest $request)
    {
        try {
            DB::beginTransaction();

            $material = Material::lockForUpdate()->find($request->material_id);
            if (!$material) {
                throw new \Exception('Material tidak ditemukan');
            }

            $quantityIn = $request->quantity_in ?? 0;
            $quantityOut = $request->quantity_out ?? 0;

            // Validate if both are zero
            if ($quantityIn === 0 && $quantityOut === 0) {
                throw new \Exception('Quantity masuk atau keluar harus diisi');
            }

            // Validate if both are positive (mutually exclusive)
            if ($quantityIn > 0 && $quantityOut > 0) {
                throw new \Exception('Hanya boleh mengisi salah satu: masuk atau keluar');
            }

            // Update stock
            if ($quantityIn > 0) {
                $this->stockService->addMaterialStock(
                    $material->id,
                    $quantityIn,
                    ['transaction_type' => 'other']
                );
            } elseif ($quantityOut > 0) {
                $this->stockService->subtractMaterialStock(
                    $material->id,
                    $quantityOut,
                    ['transaction_type' => 'other']
                );
            }

            // Create transaction
            $transaction = OtherTransaction::create([
                'transaction_number' => OtherTransaction::generateTransactionNumber(),
                'transaction_date' => $request->transaction_date ?? now(),
                'material_id' => $request->material_id,
                'material_name' => $material->name,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'need_type' => $request->need_type,
                'note' => $request->note,
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Other Transaction',
                "Transaksi lainnya {$transaction->transaction_number} - {$material->code} (masuk: {$quantityIn}, keluar: {$quantityOut})"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(OtherTransactionRequest $request, OtherTransaction $otherTransaction)
    {
        try {
            DB::beginTransaction();

            $material = Material::lockForUpdate()->find($otherTransaction->material_id);
            if (!$material) {
                throw new \Exception('Material tidak ditemukan');
            }

            // Reverse old quantities
            if ($otherTransaction->quantity_in > 0) {
                $this->stockService->subtractMaterialStock(
                    $material->id,
                    $otherTransaction->quantity_in,
                    ['transaction_type' => 'other_rollback']
                );
            } elseif ($otherTransaction->quantity_out > 0) {
                $this->stockService->addMaterialStock(
                    $material->id,
                    $otherTransaction->quantity_out,
                    ['transaction_type' => 'other_rollback']
                );
            }

            $quantityIn = $request->quantity_in ?? 0;
            $quantityOut = $request->quantity_out ?? 0;

            // Apply new quantities
            if ($quantityIn > 0) {
                $this->stockService->addMaterialStock(
                    $material->id,
                    $quantityIn,
                    ['transaction_type' => 'other']
                );
            } elseif ($quantityOut > 0) {
                $this->stockService->subtractMaterialStock(
                    $material->id,
                    $quantityOut,
                    ['transaction_type' => 'other']
                );
            }

            // Update transaction
            $otherTransaction->update([
                'transaction_date' => $request->transaction_date,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'need_type' => $request->need_type,
                'note' => $request->note,
            ]);

            $this->auditLogService->logWithUser(
                'Edit',
                'Other Transaction',
                "Transaksi lainnya {$otherTransaction->transaction_number} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil diperbarui',
                'data' => $otherTransaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(OtherTransaction $otherTransaction)
    {
        try {
            DB::beginTransaction();

            // Reverse stock
            if ($otherTransaction->quantity_in > 0) {
                $this->stockService->subtractMaterialStock(
                    $otherTransaction->material_id,
                    $otherTransaction->quantity_in,
                    ['transaction_type' => 'other_delete']
                );
            } elseif ($otherTransaction->quantity_out > 0) {
                $this->stockService->addMaterialStock(
                    $otherTransaction->material_id,
                    $otherTransaction->quantity_out,
                    ['transaction_type' => 'other_delete']
                );
            }

            $this->auditLogService->logWithUser(
                'Hapus',
                'Other Transaction',
                "Transaksi lainnya {$otherTransaction->transaction_number} dihapus"
            );

            $otherTransaction->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $transactions = OtherTransaction::search($keyword)->limit(20)->get();
        return response()->json($transactions);
    }

    public function import(Request $request)
    {
        // Import functionality
        return response()->json(['message' => 'Import functionality coming soon']);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }
}