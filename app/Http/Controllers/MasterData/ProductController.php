<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ProductRequest;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Services\AuditLogService;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        $categories = Category::all();
        $units = Unit::all();

        if ($request->ajax()) {
            return response()->json([
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ]);
        }

        return view('masterdata.products', compact('products', 'categories', 'units'));
    }

    /**
     * Store a newly created product.
     */
    public function store(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = Product::create([
                'sku' => $request->sku ?? Product::generateSku(),
                'name' => $request->name,
                'category' => $request->category,
                'unit' => $request->unit ?? 'Pcs',
                'packaging' => $request->packaging ?? 'Box',
                'packaging_qty' => $request->packaging_qty ?? 0,
                'created_by' => auth()->id(),
            ]);

            $this->auditLogService->logWithUser(
                'Tambah',
                'Product',
                "Produk {$product->sku} - {$product->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(ProductRequest $request, Product $product)
    {
        try {
            DB::beginTransaction();

            $product->update($request->validated());

            $this->auditLogService->logWithUser(
                'Edit',
                'Product',
                "Produk {$product->sku} - {$product->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified product (Soft Delete).
     */
    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Product',
                "Produk {$product->sku} - {$product->name} dihapus"
            );

            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Products from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480'
        ]);

        $importService = app(ImportService::class);
        $result = $importService->importProducts($request->file('file'));

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
     * Export Products to Excel.
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
     * Search products.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $products = Product::search($keyword)->limit(20)->get();
        return response()->json($products);
    }

    /**
     * Toggle product status (Active/Inactive).
     */
    public function toggleStatus(Product $product)
    {
        try {
            DB::beginTransaction();

            $oldStatus = $product->status ?? 'Active';
            $product->status = $product->status === 'Active' ? 'Inactive' : 'Active';
            $product->save();

            $this->auditLogService->logWithUser(
                'Status Update',
                'Product',
                "Produk {$product->sku} - {$product->name} status diubah dari {$oldStatus} menjadi {$product->status}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status produk berhasil diubah',
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status produk: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product data for dropdown.
     */
    public function getActiveProducts(Request $request)
    {
        $query = Product::where('status', 'Active');
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('sku', 'LIKE', "%{$request->search}%")
                  ->orWhere('name', 'LIKE', "%{$request->search}%");
            });
        }

        $products = $query->orderBy('sku')->limit(20)->get();
        return response()->json($products);
    }
}