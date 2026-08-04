<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\MaterialRequest;
use App\Models\Material;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of materials.
     */
    public function index(Request $request)
    {
        $query = Material::with(['supplier']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $materials = $query->orderBy('created_at', 'desc')->paginate(15);
        $suppliers = Supplier::active()->get();

        if ($request->ajax()) {
            return response()->json([
                'data' => $materials->items(),
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'total' => $materials->total(),
            ]);
        }

        return view('masterdata.materials', compact('materials', 'suppliers'));
    }

    /**
     * Store a newly created material.
     */
    public function store(MaterialRequest $request)
    {
        try {
            DB::beginTransaction();

            $material = Material::create([
                'code' => $request->code ?? Material::generateCode(),
                'name' => $request->name,
                'specification' => $request->specification,
                'unit' => $request->unit,
                'stock_initial' => $request->stock_initial ?? 0,
                'stock_current' => $request->stock_initial ?? 0,
                'stock_minimum' => $request->stock_minimum ?? 100,
                'supplier_id' => $request->supplier_id,
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Material',
                "Material {$material->code} - {$material->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil ditambahkan',
                'data' => $material
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified material.
     */
    public function update(MaterialRequest $request, Material $material)
    {
        try {
            DB::beginTransaction();

            $oldData = $material->toArray();
            $material->update($request->validated());

            // Update stock_current if stock_initial changed
            if ($request->has('stock_initial')) {
                $diff = $request->stock_initial - $oldData['stock_initial'];
                $material->stock_current += $diff;
                $material->save();
            }

            $this->auditLogService->logWithUser(
                'Edit',
                'Material',
                "Material {$material->code} - {$material->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil diperbarui',
                'data' => $material
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified material (Soft Delete).
     */
    public function destroy(Material $material)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Material',
                "Material {$material->code} - {$material->name} dihapus"
            );

            $material->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Material berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Materials from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importMaterials($request->file('file'));

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
     * Export Materials to Excel.
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
     * Search materials.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $materials = Material::search($keyword)->limit(20)->get();
        return response()->json($materials);
    }

    /**
     * Toggle material status (Active/Inactive).
     */
    public function toggleStatus(Material $material)
    {
        try {
            DB::beginTransaction();

            $oldStatus = $material->status ?? 'Active';
            $material->status = $material->status === 'Active' ? 'Inactive' : 'Active';
            $material->save();

            $this->auditLogService->logWithUser(
                'Status Update',
                'Material',
                "Material {$material->code} - {$material->name} status diubah dari {$oldStatus} menjadi {$material->status}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status material berhasil diubah',
                'data' => $material
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get material data for dropdown.
     */
    public function getActiveMaterials(Request $request)
    {
        $query = Material::where('status', 'Active');
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('code', 'LIKE', "%{$request->search}%")
                  ->orWhere('name', 'LIKE', "%{$request->search}%");
            });
        }

        $materials = $query->orderBy('code')->limit(20)->get();
        return response()->json($materials);
    }
}