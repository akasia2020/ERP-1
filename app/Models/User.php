<?php

namespace App\Models;

// UBAH baris ini:
// use Illuminate\Database\Eloquent\Model;
// MENJADI:
use Illuminate\Foundation\Auth\User as Authenticatable;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// UBAH baris ini:
// class User extends Model
// MENJADI:
class User extends Authenticatable
{
    // SEMUA kode lainnya TETAP SAMA, tidak ada perubahan
    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'role_id',
        'status',
        'last_login_at'
    ];

    protected $hidden = [
        'password'
    ];

    // Semua method tetap sama persis
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public static function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf kapital';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf kecil';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung angka';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password harus mengandung karakter khusus';
        }
        
        return $errors;
    }
}