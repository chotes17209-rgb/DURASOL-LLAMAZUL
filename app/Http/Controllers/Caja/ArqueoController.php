<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Arqueo;
use App\Services\CajaChicaService;
use App\Support\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Arqueo de efectivo: conteo de billetes y monedas contra el saldo del sistema. */
class ArqueoController extends Controller
{
    public function __construct(private readonly CajaChicaService $cajaChica) {}

    public function index(Request $request): View
    {
        $fecha = $request->date('fecha') ?? today();
        $caja = array_key_exists((string) $request->caja, Arqueo::CAJAS) ? $request->caja : 'general';
        $arqueo = Arqueo::where('fecha', $fecha->toDateString())->where('caja', $caja)->first();
        $saldoSistema = $this->cajaChica->saldoSistema($caja, $fecha);
        $historial = Arqueo::with('user')->orderByDesc('fecha')->orderBy('caja')->limit(30)->get();

        return view('caja.arqueos.index', compact('fecha', 'caja', 'arqueo', 'saldoSistema', 'historial'));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'caja' => ['required', Rule::in(array_keys(Arqueo::CAJAS))],
            'cantidades' => ['array'],
            'cantidades.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);
        $arqueo = $this->cajaChica->registrarArqueo($request->date('fecha'), $datos['caja'], $datos['cantidades'] ?? [], $datos['observaciones'] ?? null);

        $estado = abs((float) $arqueo->diferencia) < 0.005 ? 'El efectivo cuadra con el sistema.'
            : ((float) $arqueo->diferencia > 0 ? 'Sobrante de S/ ' : 'Faltante de S/ ').number_format(abs((float) $arqueo->diferencia), 2);

        return $this->ok('Arqueo registrado. '.$estado, ['reloadPage' => true]);
    }

    public function show(Request $request, Arqueo $arqueo)
    {
        $arqueo->load('user');
        if (in_array($request->formato, ['pdf', 'xlsx'], true)) {
            return $this->reporte($arqueo)->descargar($request->formato, 'arqueo-'.$arqueo->caja.'-'.$arqueo->fecha->toDateString());
        }

        return view('caja.arqueos.show', compact('arqueo'));
    }

    public function destroy(Arqueo $arqueo): JsonResponse
    {
        $arqueo->delete();

        return $this->ok('Arqueo eliminado.', ['reloadPage' => true]);
    }

    private function reporte(Arqueo $a): Reporte
    {
        $reporte = (new Reporte('Arqueo de efectivo', $a->nombreCaja().' · '.$a->fecha->format('d/m/Y')))
            ->datos(['Caja' => $a->nombreCaja(), 'Fecha' => $a->fecha->format('d/m/Y'), 'Registró' => $a->user?->name]);
        foreach (config('erp.denominaciones') as $tipo => $valores) {
            $filas = [];
            $subtotal = 0;
            foreach ($valores as $valor) {
                $cantidad = (int) ($a->detalle[CajaChicaService::clave($valor)] ?? 0);
                $filas[] = ['S/ '.number_format($valor, 2), $cantidad, $cantidad * $valor];
                $subtotal += $cantidad * $valor;
            }
            $reporte->tabla(ucfirst($tipo), ['Denominación' => 'texto', 'Cantidad' => 'entero', 'Importe' => 'decimal'], $filas, ['Subtotal', '', $subtotal]);
        }

        return $reporte->tabla('Resultado', ['Concepto' => 'texto', 'Importe' => 'decimal'], [
            ['Total contado', $a->total_contado],
            ['Saldo según sistema', $a->saldo_sistema],
        ], [(float) $a->diferencia >= 0 ? 'SOBRANTE' : 'FALTANTE', abs((float) $a->diferencia)], $a->observaciones);
    }
}
