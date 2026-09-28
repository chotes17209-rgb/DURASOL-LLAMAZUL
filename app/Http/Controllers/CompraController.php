<?php

namespace App\Http\Controllers;

use App\Models\CompraPlanta;
use App\Models\CuotaCompra;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Services\CompraService;
use App\Support\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Compras en planta: cuadro mensual por empresa (cuota, avance, comparativa) y registro de compras. */
class CompraController extends Controller
{
    public function __construct(private readonly CompraService $compras) {}

    public function index(Request $request)
    {
        $mes = $this->mes($request);
        $empresas = $this->compras->empresas();
        $vista = $request->input('empresa', (string) $empresas->first()?->id);
        $empresaId = $vista === 'global' ? null : (int) $vista;
        $empresa = $empresaId ? $empresas->firstWhere('id', $empresaId) : null;
        $cuadro = $this->compras->cuadro($mes, $empresaId);

        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            return $this->reporte($cuadro, $empresa?->nombre ?? 'Global')->descargar($request->formato, 'compras-'.($empresa?->nombre ?? 'global').'-'.$mes->format('Y-m'));
        }

        $registros = CompraPlanta::with(['empresa', 'producto', 'instalacion', 'user'])
            ->whereBetween('fecha', [$mes->copy()->startOfMonth()->toDateString(), $mes->copy()->endOfMonth()->toDateString()])
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('fecha')->orderBy('empresa_id')->orderBy('producto_id')->get();

        return view('compras.index', compact('cuadro', 'empresas', 'vista', 'empresa', 'registros'));
    }

    public function create(Request $request): View
    {
        $fecha = $request->date('fecha');

        return $this->form(new CompraPlanta([
            'fecha' => $fecha && $fecha->lte(today()) ? $fecha : today(),
            'empresa_id' => is_numeric($request->empresa) ? (int) $request->empresa : null,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->conPrecio($request, $this->validar($request));
        CompraPlanta::create($datos + ['origen' => 'manual', 'user_id' => auth()->id()]);

        return $this->ok('Compra registrada.', ['reloadPage' => true]);
    }

    public function edit(CompraPlanta $compra): View
    {
        abort_unless($compra->editable(), 422, 'Esta compra viene del parte diario; corríjala en el parte.');

        return $this->form($compra);
    }

    public function update(Request $request, CompraPlanta $compra): JsonResponse
    {
        abort_unless($compra->editable(), 422, 'Esta compra viene del parte diario; corríjala en el parte.');
        $datos = $this->validar($request);
        if (! $request->user()->can('ver-precios-compra')) {
            // Logística no ve ni modifica precios: se recalcula solo si cambia lo que define el precio.
            $datos['precio_unitario'] = $compra->fill($datos)->isDirty(['fecha', 'empresa_id', 'producto_id', 'instalacion_id'])
                ? $this->compras->precio((int) $datos['empresa_id'], (int) $datos['producto_id'], $datos['instalacion_id'] ?? null, $datos['fecha'])
                : $compra->getOriginal('precio_unitario');
        }
        $compra->update($datos);

        return $this->ok('Compra actualizada.', ['reloadPage' => true]);
    }

    public function destroy(CompraPlanta $compra): JsonResponse
    {
        abort_unless($compra->editable(), 422, 'Esta compra viene del parte diario; corríjala en el parte.');
        $compra->delete();

        return $this->ok('Compra eliminada.', ['reloadPage' => true]);
    }

    /** Precio sugerido para el formulario (instalación o promedio de la empresa a la fecha). */
    public function precio(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('ver-precios-compra'), 403);
        $request->validate(['empresa_id' => ['required', 'integer'], 'producto_id' => ['required', 'integer'], 'fecha' => ['required', 'date']]);
        $precio = $this->compras->precio($request->integer('empresa_id'), $request->integer('producto_id'), $request->integer('instalacion_id') ?: null, $request->date('fecha')->toDateString());

        return response()->json(['precio' => $precio]);
    }

    public function cuotas(Request $request): View
    {
        $mes = $this->mes($request);
        $valores = CuotaCompra::where('mes', $mes->format('Y-m'))->get()->mapWithKeys(fn ($c) => [$c->empresa_id.'-'.$c->producto_id => $c->cantidad]);

        return view('compras.cuotas', [
            'mes' => $mes, 'valores' => $valores, 'empresas' => $this->compras->empresas(),
            'productos' => Producto::whereIn('codigo', CompraService::PRODUCTOS)->orderBy('orden')->get(),
        ]);
    }

    public function guardarCuotas(Request $request): JsonResponse
    {
        $request->validate(['mes' => ['required', 'date_format:Y-m'], 'cuotas' => ['array'], 'cuotas.*.*' => ['nullable', 'integer', 'min:0', 'max:9999999']]);
        foreach ($request->input('cuotas', []) as $empresaId => $porProducto) {
            foreach ($porProducto as $productoId => $cantidad) {
                $clave = ['mes' => $request->mes, 'empresa_id' => (int) $empresaId, 'producto_id' => (int) $productoId];
                if ($cantidad === null || $cantidad === '') {
                    CuotaCompra::where($clave)->delete();
                } else {
                    CuotaCompra::updateOrCreate($clave, ['cantidad' => (int) $cantidad]);
                }
            }
        }

        return $this->ok('Cuotas del mes guardadas.', ['reloadPage' => true]);
    }

    /** Si quien registra no maneja precios, se toma el precio vigente de la instalación o de la empresa. */
    private function conPrecio(Request $request, array $datos): array
    {
        if (! $request->user()->can('ver-precios-compra') || ! isset($datos['precio_unitario'])) {
            $datos['precio_unitario'] = $this->compras->precio((int) $datos['empresa_id'], (int) $datos['producto_id'], $datos['instalacion_id'] ?? null, $datos['fecha']);
        }

        return $datos;
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'producto_id' => ['required', Rule::exists('productos', 'id')->whereIn('codigo', CompraService::PRODUCTOS)],
            'instalacion_id' => ['nullable', 'exists:instalaciones,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'documento' => ['nullable', 'string', 'max:40'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ], [], ['empresa_id' => 'empresa', 'producto_id' => 'presentación', 'instalacion_id' => 'instalación', 'precio_unitario' => 'precio unitario']);
    }

    private function form(CompraPlanta $compra): View
    {
        return view('compras.form', [
            'compra' => $compra,
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'productos' => Producto::whereIn('codigo', CompraService::PRODUCTOS)->orderBy('orden')->pluck('codigo', 'id'),
            'instalaciones' => Instalacion::with('empresa')->orderBy('codigo')->get()
                ->mapWithKeys(fn ($i) => [$i->id => $i->codigo.' · '.($i->responsable ?? $i->nombre).' ('.$i->empresa?->nombre.')']),
        ]);
    }

    private function mes(Request $request): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $request->mes) ? Carbon::parse($request->mes.'-01') : today()->startOfMonth();
    }

    private function reporte(array $c, string $nombre): Reporte
    {
        $mes = ucfirst($c['mes']->translatedFormat('F Y'));
        $filas = $c['dias']->map(fn ($d) => [$d['fecha']->format('d/m/Y'), $d['cantidades']['S10'] ?? 0, $d['cantidades']['S45'] ?? 0, $d['cantidades']['M10'] ?? 0]);
        $anterior = ucfirst($c['anterior']->translatedFormat('F'));
        $actual = ucfirst($c['mes']->translatedFormat('F'));

        return (new Reporte('Compras en planta · '.$nombre, $mes))
            ->tabla('Compras diarias', ['Fecha' => 'texto', 'Solgas S10' => 'entero', 'Solgas S45' => 'entero', 'Masgas M10' => 'entero'], $filas,
                ['TOTAL', $c['total']['S10'], $c['total']['S45'], $c['total']['M10']])
            ->tabla('Cuota del mes', ['Producto' => 'texto', 'Cuota' => 'entero', 'Avance' => 'entero', 'Diferencia' => 'entero', '% avance' => 'decimal'],
                collect($c['cuota'])->map(fn ($q, $k) => [$k, $q['cuota'], $q['avance'], $q['diferencia'], $q['porcentaje']])->values())
            ->tabla('Comparativa con el mes anterior', ['Producto' => 'texto', $anterior => 'entero', $actual => 'entero', 'Sube / baja' => 'entero', '%' => 'decimal'],
                collect($c['comparativa'])->map(fn ($q, $k) => [$k === 'TOTAL' ? 'TOTAL (base 10 kg)' : $k, $q['anterior'], $q['actual'], $q['variacion'], $q['porcentaje']])->values());
    }
}
