<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function export(Request $request)
    {
        $type = $request->input('type', 'stock');
        $format = $request->input('format', 'excel');
        $filters = $request->all();

        $data = $this->reportService->getExportData($type, $filters);

        if ($format === 'excel') {
            return $this->exportExcel($data, $type);
        } elseif ($format === 'pdf') {
            return $this->exportPdf($data, $type);
        }

        return response()->json(['error' => 'Invalid format'], 400);
    }

    protected function exportExcel(array $data, string $type)
    {
        // This will be implemented with Laravel Excel
        // For now, return CSV
        $filename = $type . '_report_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    protected function exportPdf(array $data, string $type)
    {
        // This will be implemented with DomPDF
        // For now, return a simple message
        return response()->json([
            'message' => 'PDF export will be available soon',
            'type' => $type,
            'data_count' => count($data)
        ]);
    }

    public function print(Request $request)
    {
        $type = $request->input('type', 'stock');
        $filters = $request->all();

        $data = $this->reportService->getExportData($type, $filters);

        return view('reports.print', compact('data', 'type'));
    }
}