<?php

namespace App\Http\Controllers\Produksi;

use App\Http\Controllers\Controller;
use App\Models\ProductionPlan;
use App\Models\Timeline;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'planningIn' => ProductionPlan::where('status', 'Draft')->count(),
            'running' => ProductionPlan::where('status', 'Proses')->count(),
            'done' => ProductionPlan::where('status', 'Selesai')->count(),
            'recentActivities' => Timeline::whereHas('user', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'produksi');
                });
            })->orderBy('created_at', 'desc')->limit(5)->get()
        ];

        return view('produksi.dashboard', $data);
    }
}