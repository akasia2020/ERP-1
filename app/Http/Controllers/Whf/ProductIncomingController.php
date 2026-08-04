<?php

namespace App\Http\Controllers\Whf;

use App\Http\Controllers\Controller;
use App\Models\WhfIncoming;
use App\Models\FinishGood;
use App\Services\WhfService;
use Illuminate\Http\Request;

class ProductIncomingController extends Controller
{
    protected WhfService $whfService;

    public function __construct(WhfService $whfService)
    {
        $this->whfService = $whfService;
    }

    public function index(Request $request)
    {
        $query = WhfIncoming::with(['product', 'finishGood', 'creator']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        $incomings = $query->orderBy('created_at', 'desc')->paginate(15);

        if ($request->ajax()) {
            return response()->json($incomings);
        }

        return view('whf.product-incoming', compact('incomings'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'finish_good_id' => 'required|exists:finish_goods,id',
            ]);

            $incoming = $this->whfService->receiveFinishGood($request->finish_good_id);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diterima WHF',
                'data' => $incoming
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerima produk: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        $incomings = WhfIncoming::with(['product', 'finishGood'])
            ->orderBy('incoming_date', 'desc')
            ->limit(100)
            ->get();

        return response()->json($incomings);
    }

    public function export(Request $request)
    {
        // Export functionality
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    public function search(Request $request)
    {
        $keyword = $request->get('q', '');
        $incomings = WhfIncoming::search($keyword)->limit(20)->get();
        return response()->json($incomings);
    }
}