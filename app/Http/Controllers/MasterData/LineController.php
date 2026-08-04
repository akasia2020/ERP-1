<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\LineRequest;
use App\Models\ProductionLine;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LineController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of production lines.
     */
    public function index(Request $request)
    {
        $query = ProductionLine::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $lines = $query->orderBy('created_at', 'desc')->paginate(15);

        if ($request->ajax()) {
            return response()->json([
                'data' => $lines->items(),
                'current_page' => $lines->currentPage(),
                'last_page' => $lines->lastPage(),
                'total' => $lines->total(),
            ]);
        }

        return view('masterdata.lines', compact('lines'));
    }

    /**
     * Store a newly created production line.
     */
    public function store(LineRequest $request)
    {
        try {
            DB::beginTransaction();

            $line = ProductionLine::create([
                'code' => $request->code ?? ProductionLine::generateCode(),
                'name' => $request->name,
                'pic' => $request->pic,
                'status' => $request->status ?? 'Aktif',
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Line',
                "Line {$line->code} - {$line->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Line produksi berhasil ditambahkan',
                'data' => $line
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan line: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified production line.
     */
    public function update(LineRequest $request, ProductionLine $line)
    {
        try {
            DB::beginTransaction();

            $line->update($request->validated());

            $this->auditLogService->logWithUser(
                'Edit',
                'Line',
                "Line {$line->code} - {$line->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Line produksi berhasil diperbarui',
                'data' => $line
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui line: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified production line (Soft Delete).
     */
    public function destroy(ProductionLine $line)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Line',
                "Line {$line->code} - {$line->name} dihapus"
            );

            $line->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Line produksi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus line: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle line status.
     */
    public function toggleStatus(ProductionLine $line)
    {
        try {
            DB::beginTransaction();

            $line->status = $line->status === 'Aktif' ? 'Tidak Aktif' : 'Aktif';
            $line->save();

            $this->auditLogService->logWithUser(
                'Status Update',
                'Line',
                "Line {$line->code} - {$line->name} status menjadi {$line->status}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status line berhasil diubah',
                'data' => $line
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status line: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Production Lines from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importLines($request->file('file'));

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
     * Export Production Lines to Excel.
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
     * Search production lines.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $lines = ProductionLine::search($keyword)->limit(20)->get();
        return response()->json($lines);
    }

    /**
     * Get active production lines for dropdown.
     */
    public function getActiveLines(Request $request)
    {
        $query = ProductionLine::where('status', 'Aktif');
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('code', 'LIKE', "%{$request->search}%")
                  ->orWhere('name', 'LIKE', "%{$request->search}%");
            });
        }

        $lines = $query->orderBy('code')->limit(20)->get();
        return response()->json($lines);
    }
}