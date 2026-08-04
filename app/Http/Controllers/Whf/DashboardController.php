<?php

namespace App\Http\Controllers\Whf;

use App\Http\Controllers\Controller;
use App\Models\WhfStock;
use App\Models\WhfIncoming;
use App\Models\Timeline;
use App\Services\WhfService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected WhfService $whfService;

    public function __construct(WhfService $whfService)
    {
        $this->whfService = $whfService;
    }

    public function index()
    {
        $summary = $this->whfService->getWhfSummary();
        
        $data = [
            'totalStock' => $summary['total_stock'],
            'totalProducts' => $summary['total_products'],
            'totalIncoming' => $summary['total_incoming'],
            'totalOutgoing' => $summary['total_outgoing'],
            'recentActivities' => Timeline::whereHas('user', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'whf');
                });
            })->orderBy('created_at', 'desc')->limit(5)->get()
        ];

        return view('whf.dashboard', $data);
    }
}