<?php

namespace App\Support;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Punto único para escribir en el historial (tabla audits).
 */
class AuditLogger
{
    private static bool $enabled = true;

    /** Ejecuta un bloque sin registrar historial (por ejemplo, la importación masiva del Excel). */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    public static function model(string $event, Model $model, array $old, array $new): void
    {
        self::write($event, $old, $new, null, $model);
    }

    /** Eventos que no son cambios de un modelo: login, cierre de liquidación, exportaciones... */
    public static function event(string $event, string $description, ?Model $model = null, array $data = []): void
    {
        self::write($event, [], $data, $description, $model);
    }

    private static function write(string $event, array $old, array $new, ?string $description, ?Model $model): void
    {
        if (! self::$enabled) {
            return;
        }

        $runningInConsole = app()->runningInConsole() && ! app()->runningUnitTests();

        Audit::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => self::normalize($old) ?: null,
            'new_values' => self::normalize($new) ?: null,
            'description' => $description,
            'url' => $runningInConsole ? 'consola' : mb_substr(Request::fullUrl(), 0, 500),
            'ip_address' => $runningInConsole ? null : Request::ip(),
            'user_agent' => $runningInConsole ? null : mb_substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    private static function normalize(array $values): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }
}
