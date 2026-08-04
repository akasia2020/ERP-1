<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CustomerRequest;
use App\Models\Customer;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(15);

        if ($request->ajax()) {
            return response()->json([
                'data' => $customers->items(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ]);
        }

        return view('masterdata.customers', compact('customers'));
    }

    /**
     * Store a newly created customer.
     */
    public function store(CustomerRequest $request)
    {
        try {
            DB::beginTransaction();

            $customer = Customer::create([
                'name' => $request->name,
                'domicile' => $request->domicile,
                'phone' => $request->phone,
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Customer',
                "Customer {$customer->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil ditambahkan',
                'data' => $customer
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified customer.
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        try {
            DB::beginTransaction();

            $customer->update($request->validated());

            $this->auditLogService->logWithUser(
                'Edit',
                'Customer',
                "Customer {$customer->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil diperbarui',
                'data' => $customer
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer (Soft Delete).
     */
    public function destroy(Customer $customer)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Customer',
                "Customer {$customer->name} dihapus"
            );

            $customer->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Customers from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importCustomers($request->file('file'));

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
     * Export Customers to Excel.
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
     * Search customers.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $customers = Customer::search($keyword)->limit(20)->get();
        return response()->json($customers);
    }

    /**
     * Get customers for dropdown.
     */
    public function getActiveCustomers(Request $request)
    {
        $query = Customer::query();
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('domicile', 'LIKE', "%{$request->search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get();
        return response()->json($customers);
    }
}