<?php

namespace App\Models;

use App\Enums\Rol;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected array $auditExclude = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Rol::class,
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Rol::Admin;
    }

    /** El administrador tiene acceso a todo; el resto solo a los roles indicados. */
    public function hasRole(Rol|string ...$roles): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->role === ($role instanceof Rol ? $role : Rol::from($role))) {
                return true;
            }
        }

        return false;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
