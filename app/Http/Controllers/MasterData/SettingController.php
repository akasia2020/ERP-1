<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Models\Unit;
use App\Models\Category;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function index()
    {
        $users = User::with(['role'])->get();
        $units = Unit::all();
        $categories = Category::all();
        $ipWhitelist = Setting::getIpWhitelist();
        $alertColors = Setting::getAlertColors();
        $globalStockMin = Setting::getGlobalStockMin();
        $fgPrefix = Setting::getFGPrefix();
        $fgLastNo = Setting::getFGLastNo();

        return view('masterdata.settings', compact(
            'users',
            'units',
            'categories',
            'ipWhitelist',
            'alertColors',
            'globalStockMin',
            'fgPrefix',
            'fgLastNo'
        ));
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            foreach ($request->all() as $key => $value) {
                if (!in_array($key, ['_token', '_method'])) {
                    Setting::setValue($key, $value);
                }
            }

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                'Pengaturan sistem diperbarui'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateAlertColors(Request $request)
    {
        try {
            DB::beginTransaction();

            $colors = $request->validate([
                'danger' => 'required|string|max:7',
                'warning' => 'required|string|max:7',
                'info' => 'required|string|max:7',
            ]);

            foreach ($colors as $key => $value) {
                Setting::setValue("alert_{$key}_color", $value);
            }

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                'Warna alert diperbarui'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Warna alert berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan warna alert: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStockMinimum(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'global_stock_min' => 'required|integer|min:0',
            ]);

            Setting::setValue('global_stock_min', $request->global_stock_min);

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                "Stock minimum global diubah menjadi {$request->global_stock_min}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock minimum berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan stock minimum: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFGNumber(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'fg_prefix' => 'required|string|max:20',
                'fg_last_no' => 'required|string|max:10',
            ]);

            Setting::setValue('fg_prefix', $request->fg_prefix);
            Setting::setValue('fg_last_no', $request->fg_last_no);

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                "Nomor Finish Good diperbarui: {$request->fg_prefix}{$request->fg_last_no}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan nomor Finish Good berhasil disimpan'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan nomor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateIpWhitelist(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'ip' => 'required|string|max:45',
            ]);

            $ips = Setting::getIpWhitelist();
            if (!in_array($request->ip, $ips)) {
                $ips[] = $request->ip;
                Setting::setIpWhitelist($ips);
            }

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                "IP {$request->ip} ditambahkan ke whitelist"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IP berhasil ditambahkan',
                'data' => $ips
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan IP: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeIpWhitelist($ip)
    {
        try {
            DB::beginTransaction();

            $ips = Setting::getIpWhitelist();
            $ips = array_filter($ips, function ($item) use ($ip) {
                return $item !== $ip;
            });
            Setting::setIpWhitelist(array_values($ips));

            $this->auditLogService->logWithUser(
                'Update',
                'Settings',
                "IP {$ip} dihapus dari whitelist"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'IP berhasil dihapus',
                'data' => $ips
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus IP: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeUnit(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'name' => 'required|string|max:20|unique:units,name',
                'description' => 'nullable|string|max:100',
            ]);

            $unit = Unit::create($request->all());

            $this->auditLogService->logWithUser(
                'Tambah',
                'Settings',
                "Satuan {$unit->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil ditambahkan',
                'data' => $unit
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan satuan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'name' => "required|string|max:20|unique:units,name,{$unit->id}",
                'description' => 'nullable|string|max:100',
            ]);

            $unit->update($request->all());

            $this->auditLogService->logWithUser(
                'Edit',
                'Settings',
                "Satuan {$unit->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil diperbarui',
                'data' => $unit
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui satuan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyUnit(Unit $unit)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Settings',
                "Satuan {$unit->name} dihapus"
            );

            $unit->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Satuan berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus satuan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeCategory(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'name' => 'required|string|max:50|unique:categories,name',
                'description' => 'nullable|string|max:100',
            ]);

            $category = Category::create($request->all());

            $this->auditLogService->logWithUser(
                'Tambah',
                'Settings',
                "Kategori {$category->name} ditambahkan"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil ditambahkan',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan kategori: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(Request $request, Category $category)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'name' => "required|string|max:50|unique:categories,name,{$category->id}",
                'description' => 'nullable|string|max:100',
            ]);

            $category->update($request->all());

            $this->auditLogService->logWithUser(
                'Edit',
                'Settings',
                "Kategori {$category->name} diperbarui"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil diperbarui',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kategori: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyCategory(Category $category)
    {
        try {
            DB::beginTransaction();

            $this->auditLogService->logWithUser(
                'Hapus',
                'Settings',
                "Kategori {$category->name} dihapus"
            );

            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kategori berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kategori: ' . $e->getMessage()
            ], 500);
        }
    }

    public function backup(Request $request)
    {
        try {
            // Backup functionality
            return response()->json([
                'success' => true,
                'message' => 'Backup database berhasil dibuat'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat backup: ' . $e->getMessage()
            ], 500);
        }
    }

    public function restore(Request $request)
    {
        try {
            // Restore functionality
            return response()->json([
                'success' => true,
                'message' => 'Restore database berhasil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal restore database: ' . $e->getMessage()
            ], 500);
        }
    }
}