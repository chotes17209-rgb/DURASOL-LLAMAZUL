<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Historial completo de todo lo que se hace en el sistema. */
class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $audits = Audit::with(['user', 'auditable'])
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->modulo, fn ($q, $m) => $q->where('auditable_type', $m))
            ->when($request->evento, fn ($q, $e) => $q->where('event', $e))
            ->when($request->desde, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($request->hasta, fn ($q, $h) => $q->where('created_at', '<=', $h.' 23:59:59'))
            ->when($request->q, function ($q, $t) {
                // Las columnas JSON se comparan como texto (sintaxis distinta en MySQL y PostgreSQL).
                $comoTexto = DB::getDriverName() === 'pgsql' ? '%s::text' : 'CAST(%s AS CHAR)';
                $q->where(fn ($w) => $w->where('description', 'like', "%$t%")
                    ->orWhereRaw(sprintf($comoTexto, 'new_values').' like ?', ["%$t%"])
                    ->orWhereRaw(sprintf($comoTexto, 'old_values').' like ?', ["%$t%"]));
            })
            ->latest('id')->paginate(40)->withQueryString();

        $usuarios = User::orderBy('name')->pluck('name', 'id');
        $modulos = config('erp.modelos');
        $eventos = ['created' => 'Creó', 'updated' => 'Modificó', 'deleted' => 'Eliminó', 'restored' => 'Restauró', 'cerrada' => 'Cerró liquidación', 'reabierta' => 'Reabrió',
            'anulada' => 'Anuló', 'recibida' => 'Recibió guía', 'retornado' => 'Retorno de despacho', 'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión', 'login_failed' => 'Acceso fallido', 'exportacion' => 'Exportación'];

        return $this->tableOrPage($request, 'historial.index', 'historial._table', compact('audits', 'usuarios', 'modulos', 'eventos'));
    }
}
