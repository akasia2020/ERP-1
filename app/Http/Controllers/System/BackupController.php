<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    protected BackupService $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $backups = $this->backupService->listBackups();
        $totalSize = $this->backupService->getBackupSize();
        
        return view('system.backup', compact('backups', 'totalSize'));
    }

    public function create()
    {
        $result = $this->backupService->createBackup();
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 500);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'filename' => 'required|string'
        ]);

        $result = $this->backupService->restoreBackup($request->filename);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Restore completed successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 500);
    }

    public function download($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        
        if (!file_exists($path)) {
            abort(404, 'Backup file not found');
        }

        return response()->download($path);
    }

    public function destroy($filename)
    {
        $result = $this->backupService->deleteBackup($filename);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 500);
    }
}