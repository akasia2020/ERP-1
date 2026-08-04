<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ProductionPlan;
use App\Models\Timeline;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'totalMaterial' => Material::count(),
            'lowStock' => Material::whereRaw('stock_current < stock_minimum')->count(),
            'planningActive' => ProductionPlan::whereIn('status', ['Draft', 'Proses'])->count(),
            'recentActivities' => Timeline::whereHas('user', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'gudang');
                });
            })->orderBy('created_at', 'desc')->limit(5)->get()
        ];

        return view('gudang.dashboard', $data);
    }
}   