<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackupService
{
    protected AuditLogService $auditLogService;
    protected string $backupPath;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
        $this->backupPath = storage_path('app/backups');
        
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    public function createBackup(): array
    {
        try {
            $timestamp = Carbon::now()->format('Ymd_His');
            $filename = "backup_{$timestamp}.sql";
            $fullPath = $this->backupPath . '/' . $filename;

            // Get database configuration
            $config = config('database.connections.pgsql');
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? '5432';

            // Build pg_dump command
            $command = sprintf(
                'PGPASSWORD="%s" pg_dump -h %s -p %s -U %s -d %s > "%s" 2>&1',
                $password,
                $host,
                $port,
                $username,
                $database,
                $fullPath
            );

            // Execute backup
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Backup failed: ' . implode("\n", $output));
            }

            // Log the backup
            $this->auditLogService->logWithUser(
                'Backup',
                'System',
                "Database backup created: {$filename}"
            );

            // Clean old backups (keep last 30)
            $this->cleanOldBackups(30);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $fullPath,
                'size' => filesize($fullPath),
                'message' => 'Backup created successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            
            $this->auditLogService->logWithUser(
                'Backup Failed',
                'System',
                "Database backup failed: " . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }

    public function restoreBackup(string $filename): array
    {
        try {
            $fullPath = $this->backupPath . '/' . $filename;

            if (!file_exists($fullPath)) {
                throw new \Exception("Backup file not found: {$filename}");
            }

            // Get database configuration
            $config = config('database.connections.pgsql');
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            $host = $config['host'] ?? 'localhost';
            $port = $config['port'] ?? '5432';

            // Build psql restore command
            $command = sprintf(
                'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s < "%s" 2>&1',
                $password,
                $host,
                $port,
                $username,
                $database,
                $fullPath
            );

            // Execute restore
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Restore failed: ' . implode("\n", $output));
            }

            // Log the restore
            $this->auditLogService->logWithUser(
                'Restore',
                'System',
                "Database restored from backup: {$filename}"
            );

            return [
                'success' => true,
                'message' => 'Restore completed successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Restore failed: ' . $e->getMessage());
            
            $this->auditLogService->logWithUser(
                'Restore Failed',
                'System',
                "Database restore failed: " . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }

    public function listBackups(): array
    {
        $files = glob($this->backupPath . '/backup_*.sql');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created_at' => filemtime($file),
                'created_at_human' => Carbon::createFromTimestamp(filemtime($file))->diffForHumans(),
            ];
        }

        // Sort by newest first
        usort($backups, function ($a, $b) {
            return $b['created_at'] - $a['created_at'];
        });

        return $backups;
    }

    protected function cleanOldBackups(int $keep = 30): void
    {
        $backups = $this->listBackups();
        
        if (count($backups) > $keep) {
            $toDelete = array_slice($backups, $keep);
            foreach ($toDelete as $backup) {
                if (file_exists($backup['path'])) {
                    unlink($backup['path']);
                }
            }
        }
    }

    public function getBackupSize(): int
    {
        $backups = $this->listBackups();
        $totalSize = 0;
        foreach ($backups as $backup) {
            $totalSize += $backup['size'];
        }
        return $totalSize;
    }

    public function deleteBackup(string $filename): array
    {
        try {
            $fullPath = $this->backupPath . '/' . $filename;
            
            if (!file_exists($fullPath)) {
                throw new \Exception("Backup file not found: {$filename}");
            }

            unlink($fullPath);

            $this->auditLogService->logWithUser(
                'Delete Backup',
                'System',
                "Backup file deleted: {$filename}"
            );

            return [
                'success' => true,
                'message' => 'Backup deleted successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage()
            ];
        }
    }
}