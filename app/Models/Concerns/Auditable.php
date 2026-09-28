<?php

namespace App\Models\Concerns;

use App\Models\Audit;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Registra en la tabla "audits" cada alta, cambio, borrado y restauración
 * del modelo, con los valores anteriores y nuevos.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => AuditLogger::model('created', $model, [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLogger::model('updated', $model, $model->auditableAttributes($old), $changes);
        });

        static::deleted(fn (Model $model) => AuditLogger::model('deleted', $model, $model->auditableAttributes($model->getOriginal()), []));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => AuditLogger::model('restored', $model, [], []));
        }
    }

    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable')->latest('id');
    }

    /** Quita columnas que no aportan al historial (timestamps, tokens). */
    protected function auditableAttributes(array $attributes): array
    {
        $ignored = array_merge(['created_at', 'updated_at', 'password', 'remember_token'], $this->auditExclude ?? []);

        return array_diff_key($attributes, array_flip($ignored));
    }

    /** Nombre legible del registro para mostrar en el historial. */
    public function auditLabel(): string
    {
        foreach (['codigo', 'numero_guia', 'placa', 'alias', 'nombre', 'name'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return '#'.$this->getKey();
    }
}
