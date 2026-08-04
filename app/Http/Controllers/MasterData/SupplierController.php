<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $suppliers = $query->orderBy('created_at', 'desc')->paginate(15);

        if ($request->ajax()) {
            return response()->json($suppliers);
        }

        return view('masterdata.suppliers', compact('suppliers'));
    }

    public function store(SupplierRequest $request)
    {
        try {
            DB::beginTransaction();

            $supplier = Supplier::create([
                'code' => $request->code ?? Supplier::generateCode(),
                'name' => $request->name,
                'category' => $request->category,
                'contact' => $request->contact,
                'location' => $request->location,
                'status' => $request->status ?? 'Aktif',
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Supplier',
                "Supplier {$supplier->code} - {$supplier->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil ditambahkan',
                'data' => $supplier
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan supplier: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        try {
            DB::beginTransaction();

            $supplier->update($request->validated());

            $this->auditLogService->logWithUser(
                'Edit',
                'Supplier',
                "Supplier {$supplier->code} - {$supplier->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil diperbarui',
                'data' => $supplier
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui supplier: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Supplier $supplier)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Supplier',
                "Supplier {$supplier->code} - {$supplier->name} dihapus"
            );

            $supplier->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus supplier: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus(Supplier $supplier)
    {
        try {
            DB::beginTransaction();

            $supplier->status = $supplier->status === 'Aktif' ? 'Tidak Aktif' : 'Aktif';
            $supplier->save();

            $this->auditLogService->logWithUser(
                'Status Update',
                'Supplier',
                "Supplier {$supplier->code} - {$supplier->name} status menjadi {$supplier->status}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status supplier berhasil diubah',
                'data' => $supplier
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status supplier: ' . $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $suppliers = Supplier::search($keyword)->limit(20)->get();
        return response()->json($suppliers);
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