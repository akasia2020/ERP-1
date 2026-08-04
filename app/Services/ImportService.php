<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Supplier;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Customer;
use App\Models\MaterialIncoming;
use App\Models\FinishGood;
use App\Models\ReturnModel;
use App\Models\WhfOutgoing;
use App\Models\OtherTransaction;
use App\Models\ProductionPlan;
use Exception;

class ImportService
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Read Excel file and return data
     */
    public function readExcel($file): array
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row
        array_shift($rows);

        return $rows;
    }

    /**
     * Import Suppliers
     */
    public function importSuppliers($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2; // +2 karena header di row 1

                // Skip empty row
                if (empty(array_filter($row))) continue;

                $validator = Validator::make([
                    'code' => $row[0] ?? null,
                    'name' => $row[1] ?? null,
                    'category' => $row[2] ?? null,
                    'contact' => $row[3] ?? null,
                    'location' => $row[4] ?? null,
                    'status' => $row[5] ?? 'Aktif',
                ], [
                    'code' => 'required|string|max:20|unique:suppliers,code',
                    'name' => 'required|string|max:100',
                    'category' => 'nullable|string|max:50',
                    'contact' => 'nullable|string|max:50',
                    'location' => 'nullable|string|max:100',
                    'status' => 'nullable|string|in:Aktif,Tidak Aktif',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                Supplier::create([
                    'code' => $row[0],
                    'name' => $row[1],
                    'category' => $row[2] ?? null,
                    'contact' => $row[3] ?? null,
                    'location' => $row[4] ?? null,
                    'status' => $row[5] ?? 'Aktif',
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Supplier',
                "Import {$success} supplier berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Materials
     */
    public function importMaterials($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                // Cari supplier_id berdasarkan nama supplier
                $supplier = null;
                if (!empty($row[6])) {
                    $supplier = Supplier::where('name', $row[6])->first();
                }

                $validator = Validator::make([
                    'code' => $row[0] ?? null,
                    'name' => $row[1] ?? null,
                    'specification' => $row[2] ?? null,
                    'unit' => $row[3] ?? null,
                    'stock_initial' => $row[4] ?? 0,
                    'stock_minimum' => $row[5] ?? 100,
                    'supplier_id' => $supplier ? $supplier->id : null,
                ], [
                    'code' => 'required|string|max:20|unique:materials,code',
                    'name' => 'required|string|max:100',
                    'specification' => 'nullable|string',
                    'unit' => 'required|string|max:20',
                    'stock_initial' => 'nullable|integer|min:0',
                    'stock_minimum' => 'nullable|integer|min:0',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                Material::create([
                    'code' => $row[0],
                    'name' => $row[1],
                    'specification' => $row[2] ?? null,
                    'unit' => $row[3] ?? 'Pcs',
                    'stock_initial' => $row[4] ?? 0,
                    'stock_current' => $row[4] ?? 0,
                    'stock_minimum' => $row[5] ?? 100,
                    'supplier_id' => $supplier ? $supplier->id : null,
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Material',
                "Import {$success} material berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Products
     */
    public function importProducts($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $validator = Validator::make([
                    'sku' => $row[0] ?? null,
                    'name' => $row[1] ?? null,
                    'category' => $row[2] ?? null,
                    'unit' => $row[3] ?? 'Pcs',
                    'packaging' => $row[4] ?? 'Box',
                    'packaging_qty' => $row[5] ?? 0,
                ], [
                    'sku' => 'required|string|max:20|unique:products,sku',
                    'name' => 'required|string|max:100',
                    'category' => 'nullable|string|max:50',
                    'unit' => 'nullable|string|max:20',
                    'packaging' => 'nullable|string|max:20',
                    'packaging_qty' => 'nullable|integer|min:0',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                Product::create([
                    'sku' => $row[0],
                    'name' => $row[1],
                    'category' => $row[2] ?? null,
                    'unit' => $row[3] ?? 'Pcs',
                    'packaging' => $row[4] ?? 'Box',
                    'packaging_qty' => $row[5] ?? 0,
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Product',
                "Import {$success} produk berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Production Lines
     */
    public function importLines($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $validator = Validator::make([
                    'code' => $row[0] ?? null,
                    'name' => $row[1] ?? null,
                    'pic' => $row[2] ?? null,
                    'status' => $row[3] ?? 'Aktif',
                ], [
                    'code' => 'required|string|max:20|unique:production_lines,code',
                    'name' => 'required|string|max:100',
                    'pic' => 'nullable|string|max:100',
                    'status' => 'nullable|string|in:Aktif,Tidak Aktif',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                ProductionLine::create([
                    'code' => $row[0],
                    'name' => $row[1],
                    'pic' => $row[2] ?? null,
                    'status' => $row[3] ?? 'Aktif',
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Line',
                "Import {$success} line produksi berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Customers
     */
    public function importCustomers($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $validator = Validator::make([
                    'name' => $row[0] ?? null,
                    'domicile' => $row[1] ?? null,
                    'phone' => $row[2] ?? null,
                ], [
                    'name' => 'required|string|max:100',
                    'domicile' => 'nullable|string|max:100',
                    'phone' => 'nullable|string|max:20',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                Customer::create([
                    'name' => $row[0],
                    'domicile' => $row[1] ?? null,
                    'phone' => $row[2] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Customer',
                "Import {$success} customer berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Material Incoming
     */
    public function importMaterialIncoming($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $material = Material::where('code', $row[0] ?? '')->first();
                if (!$material) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Material dengan kode '{$row[0]}' tidak ditemukan";
                    continue;
                }

                $supplier = null;
                if (!empty($row[4])) {
                    $supplier = Supplier::where('name', $row[4])->first();
                }

                $validator = Validator::make([
                    'quantity' => $row[2] ?? 0,
                    'po_number' => $row[3] ?? null,
                ], [
                    'quantity' => 'required|integer|min:1',
                    'po_number' => 'nullable|string|max:50',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                $stockBefore = $material->stock_current;
                $material->stock_current += (int)$row[2];
                $material->save();

                MaterialIncoming::create([
                    'transaction_number' => MaterialIncoming::generateTransactionNumber(),
                    'material_id' => $material->id,
                    'quantity' => (int)$row[2],
                    'stock_before' => $stockBefore,
                    'po_number' => $row[3] ?? null,
                    'supplier_id' => $supplier ? $supplier->id : null,
                    'incoming_date' => now(),
                    'created_by' => auth()->id(),
                ]);

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Material Incoming',
                "Import {$success} bahan masuk berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Finish Goods
     */
    public function importFinishGoods($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $product = Product::where('sku', $row[1] ?? '')->first();
                if (!$product) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Produk dengan sku '{$row[1]}' tidak ditemukan";
                    continue;
                }

                $line = null;
                if (!empty($row[3])) {
                    $line = ProductionLine::where('name', $row[3])->first();
                }

                $validator = Validator::make([
                    'delivery_number' => $row[2] ?? null,
                    'quantity' => $row[5] ?? 0,
                    'finish_date' => $row[6] ?? null,
                ], [
                    'delivery_number' => 'required|string|max:50',
                    'quantity' => 'required|integer|min:1',
                    'finish_date' => 'required|date',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                $finishNumber = FinishGood::generateFinishNumber();

                FinishGood::create([
                    'plan_id' => null,
                    'product_id' => $product->id,
                    'line_id' => $line ? $line->id : null,
                    'delivery_number' => $row[2],
                    'finish_number' => $finishNumber,
                    'pic' => $row[4] ?? null,
                    'quantity' => (int)$row[5],
                    'qc_status' => 'Passed',
                    'finish_date' => $row[6],
                    'status' => 'Selesai',
                    'created_by' => auth()->id(),
                ]);

                // Update product stock
                $product->stock_current += (int)$row[5];
                $product->save();

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Finish Good',
                "Import {$success} finish good berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import Returns
     */
    public function importReturns($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $product = Product::where('sku', $row[1] ?? '')->first();
                if (!$product) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Produk dengan sku '{$row[1]}' tidak ditemukan";
                    continue;
                }

                $validator = Validator::make([
                    'delivery_number' => $row[0] ?? null,
                    'quantity' => $row[3] ?? 0,
                    'store_name' => $row[2] ?? null,
                    'return_date' => $row[4] ?? null,
                ], [
                    'delivery_number' => 'required|string|max:50',
                    'quantity' => 'required|integer|min:1',
                    'store_name' => 'required|string|max:100',
                    'return_date' => 'required|date',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Check stock
                if ($product->stock_current < (int)$row[3]) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Stock produk tidak mencukupi (tersedia: {$product->stock_current})";
                    continue;
                }

                ReturnModel::create([
                    'delivery_number' => $row[0],
                    'product_id' => $product->id,
                    'store_name' => $row[2],
                    'quantity' => (int)$row[3],
                    'return_date' => $row[4],
                    'created_by' => auth()->id(),
                ]);

                // Update stock
                $product->stock_current -= (int)$row[3];
                $product->save();

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'Return',
                "Import {$success} retur berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Import WHF Outgoing
     */
    public function importWhfOutgoing($file): array
    {
        $data = $this->readExcel($file);
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $rowNum = $index + 2;
                if (empty(array_filter($row))) continue;

                $product = Product::where('sku', $row[1] ?? '')->first();
                if (!$product) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Produk dengan sku '{$row[1]}' tidak ditemukan";
                    continue;
                }

                $customer = null;
                if (!empty($row[3])) {
                    $customer = Customer::where('name', $row[3])->first();
                }

                $validator = Validator::make([
                    'delivery_number' => $row[0] ?? null,
                    'quantity' => $row[2] ?? 0,
                    'outgoing_date' => $row[4] ?? null,
                ], [
                    'delivery_number' => 'required|string|max:50',
                    'quantity' => 'required|integer|min:1',
                    'outgoing_date' => 'required|date',
                ]);

                if ($validator->fails()) {
                    $failed++;
                    $errors[] = "Baris {$rowNum}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Check WHF stock
                $whfStock = WhfStock::where('product_id', $product->id)->first();
                if (!$whfStock || $whfStock->stock_current < (int)$row[2]) {
                    $available = $whfStock ? $whfStock->stock_current : 0;
                    $failed++;
                    $errors[] = "Baris {$rowNum}: Stock WHF tidak mencukupi (tersedia: {$available})";
                    continue;
                }

                $outgoingNumber = WhfOutgoing::generateOutgoingNumber();

                WhfOutgoing::create([
                    'outgoing_number' => $outgoingNumber,
                    'delivery_number' => $row[0],
                    'product_id' => $product->id,
                    'customer_id' => $customer ? $customer->id : null,
                    'quantity' => (int)$row[2],
                    'outgoing_date' => $row[4],
                    'created_by' => auth()->id(),
                ]);

                // Update stock
                $whfStock->stock_current -= (int)$row[2];
                $whfStock->total_out += (int)$row[2];
                $whfStock->save();

                $product->stock_current -= (int)$row[2];
                $product->save();

                $success++;
            }

            $this->auditLogService->logWithUser(
                'Import',
                'WHF Outgoing',
                "Import {$success} WHF outgoing berhasil, {$failed} gagal"
            );

            DB::commit();

            return [
                'success' => true,
                'total' => $success + $failed,
                'success_count' => $success,
                'failed_count' => $failed,
                'errors' => $errors,
                'message' => "Import selesai. {$success} berhasil, {$failed} gagal."
            ];

        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }
}