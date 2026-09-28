<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Audit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values',
        'description', 'url', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Creó',
            'updated' => 'Modificó',
            'deleted' => 'Eliminó',
            'restored' => 'Restauró',
            'login' => 'Inició sesión',
            'logout' => 'Cerró sesión',
            'login_failed' => 'Intento de acceso fallido',
            'exportacion' => 'Exportó',
            'ajuste' => 'Ajustó stock',
            'cerrada' => 'Cerró',
            'reabierta' => 'Reabrió',
            'anulada' => 'Anuló',
            'cerrado' => 'Cerró',
            'reabierto' => 'Reabrió',
            default => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }

    public function eventColor(): string
    {
        return match ($this->event) {
            'created' => 'emerald',
            'updated' => 'sky',
            'deleted', 'anulada', 'login_failed' => 'rose',
            'restored', 'reabierta' => 'amber',
            'cerrada' => 'violet',
            default => 'slate',
        };
    }

    /** Nombre legible del tipo de registro (Cliente, Guía, ...). */
    public function moduleLabel(): string
    {
        if (! $this->auditable_type) {
            return 'Sistema';
        }

        return config('erp.modelos.'.$this->auditable_type, class_basename($this->auditable_type));
    }
}
