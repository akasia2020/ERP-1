<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Timeline;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthService
{
    protected AuditLogService $auditLogService;
    protected NotificationService $notificationService;

    public function __construct(
        AuditLogService $auditLogService,
        NotificationService $notificationService
    ) {
        $this->auditLogService = $auditLogService;
        $this->notificationService = $notificationService;
    }

    public function login(string $username, string $password, string $role): array
    {
        $user = User::with('role')->where('username', $username)->first();

        if (!$user) {
            return ['success' => false, 'message' => 'Username tidak ditemukan'];
        }

        if (!Hash::check($password, $user->password)) {
            return ['success' => false, 'message' => 'Password salah'];
        }

        if ($user->status !== 'Active') {
            return ['success' => false, 'message' => 'Akun tidak aktif'];
        }

        if (!$user->role || $user->role->name !== $role) {
            return ['success' => false, 'message' => 'Role tidak sesuai'];
        }

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Log activity
        $this->auditLogService->log(
            $user->id,
            'Login',
            'Authentication',
            "User {$user->name} login",
            request()->ip(),
            request()->userAgent()
        );

        // Login user
        auth()->login($user);

        return [
            'success' => true,
            'user' => $user,
            'role' => $user->role->name
        ];
    }

    public function logout(?int $userId): void
    {
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'Logout',
                'Authentication',
                "User logout",
                request()->ip(),
                request()->userAgent()
            );
        }
    }

    public function addTimeline(int $userId, string $type, string $title, ?string $description = null): void
    {
        Timeline::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'time' => now()
        ]);
    }

    public function addNotification(int $userId, string $title, ?string $message = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'is_read' => false
        ]);
    }

    public function notifyAllUsers(string $title, ?string $message = null): void
    {
        $users = User::where('status', 'Active')->get();
        foreach ($users as $user) {
            $this->addNotification($user->id, $title, $message);
        }
    }

    public function getCurrentUser(): ?User
    {
        return auth()->user();
    }

    public function getUserRole(): ?string
    {
        $user = auth()->user();
        return $user && $user->role ? $user->role->name : null;
    }

    public function authorizeAccess($resourceId, $resourceType): bool
{
    $user = auth()->user();
    $role = $user->role->name;
    
    // Define allowed access per role
    $allowedAccess = [
        'masterdata' => ['supplier', 'material', 'product', 'formula', 'line', 'customer', 'setting'],
        'gudang' => ['material_incoming', 'production_plan', 'other_transaction', 'stock_card'],
        'produksi' => ['production_running', 'finish_good', 'production_stock', 'return'],
        'whf' => ['whf_incoming', 'whf_stock', 'whf_outgoing'],
    ];
    
    $allowed = $allowedAccess[$role] ?? [];
    return in_array($resourceType, $allowed);
}
}