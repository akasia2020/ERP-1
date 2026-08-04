<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    public function log(
        ?int $userId,
        string $action,
        string $module,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent()
        ]);
    }

    public function logWithUser(
        string $action,
        string $module,
        ?string $description = null
    ): AuditLog {
        return $this->log(
            auth()->id(),
            $action,
            $module,
            $description,
            request()->ip(),
            request()->userAgent()
        );
    }

    public function getRecentLogs(int $limit = 100)
    {
        return AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLogsByModule(string $module, int $limit = 100)
    {
        return AuditLog::with('user')
            ->where('module', $module)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLogsByUser(int $userId, int $limit = 100)
    {
        return AuditLog::with('user')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // Add these methods to existing AuditLogService

public function logLogin(int $userId, bool $success, ?string $ip = null): void
{
    $action = $success ? 'Login Success' : 'Login Failed';
    $description = $success 
        ? "User {$userId} login successfully from IP: " . ($ip ?? request()->ip())
        : "Login failed for user ID: {$userId} from IP: " . ($ip ?? request()->ip());
    
    $this->log($userId, $action, 'Authentication', $description);
}

public function logLogout(int $userId, ?string $ip = null): void
{
    $this->log($userId, 'Logout', 'Authentication', "User {$userId} logged out");
}

public function logExport(int $userId, string $type, string $format): void
{
    $this->log($userId, 'Export', 'Report', "User exported {$type} report as {$format}");
}

public function logBackup(int $userId, string $backupFile): void
{
    $this->log($userId, 'Backup', 'System', "User created database backup: {$backupFile}");
}

public function logRestore(int $userId, string $backupFile): void
{
    $this->log($userId, 'Restore', 'System', "User restored database from: {$backupFile}");
}

public function logPermissionDenied(int $userId, string $route): void
{
    $this->log($userId, 'Permission Denied', 'Security', "User attempted to access: {$route}");
}

public function getAuditSummary(array $filters): array
{
    $query = AuditLog::query();
    
    if (!empty($filters['start_date'])) {
        $query->whereDate('created_at', '>=', $filters['start_date']);
    }
    if (!empty($filters['end_date'])) {
        $query->whereDate('created_at', '<=', $filters['end_date']);
    }
    if (!empty($filters['module'])) {
        $query->where('module', $filters['module']);
    }
    if (!empty($filters['action'])) {
        $query->where('action', $filters['action']);
    }
    if (!empty($filters['user_id'])) {
        $query->where('user_id', $filters['user_id']);
    }
    
    return [
        'total' => $query->count(),
        'by_action' => $query->groupBy('action')->select('action', \DB::raw('count(*) as total'))->get(),
        'by_module' => $query->groupBy('module')->select('module', \DB::raw('count(*) as total'))->get(),
        'recent' => $query->orderBy('created_at', 'desc')->limit(10)->get(),
    ];
}
}