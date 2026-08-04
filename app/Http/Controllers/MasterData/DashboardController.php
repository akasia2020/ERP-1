<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Timeline;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'supplierCount' => Supplier::where('status', 'Aktif')->count(),
            'materialCount' => Material::count(),
            'productCount' => Product::count(),
            'lineCount' => ProductionLine::where('status', 'Aktif')->count(),
            'recentActivities' => Timeline::whereHas('user', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'masterdata');
                });
            })->orderBy('created_at', 'desc')->limit(5)->get()
        ];

        return view('masterdata.dashboard', $data);
    }
}