<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(int $userId, string $title, ?string $message = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'is_read' => false
        ]);
    }

    public function createForAll(string $title, ?string $message = null): void
    {
        $users = User::where('status', 'Active')->get();
        foreach ($users as $user) {
            $this->create($user->id, $title, $message);
        }
    }

    public function createForRole(string $roleName, string $title, ?string $message = null): void
    {
        $users = User::whereHas('role', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->where('status', 'Active')->get();

        foreach ($users as $user) {
            $this->create($user->id, $title, $message);
        }
    }

    public function markAsRead(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            return true;
        }
        return false;
    }

    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function getRecent(int $userId, int $limit = 20)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}