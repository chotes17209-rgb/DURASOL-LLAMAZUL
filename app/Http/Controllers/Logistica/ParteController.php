<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParteRequest;
use App\Models\Chofer;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\Parte;
use App\Models\ParteFila;
use App\Models\Vehiculo;
use App\Services\AlmacenService;
use App\Support\AuditLogger;
use App\Support\Reporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parte diario de almacén: el área de logística registra lo que entra y sale, lleno y vacío.
 */
class ParteController extends Controller
{
    public function __construct(private readonly AlmacenService $almacen) {}

    public function index(Request $request)
    {
        $partes = Parte::withCount('filas')
            ->when($request->mes, fn ($q, $m) => $q->whereBetween('fecha', [Carbon::parse($m.'-01')->startOfMonth()->toDateString(), Carbon::parse($m.'-01')->endOfMonth()->toDateString()]))
            ->orderByDesc('fecha')->paginate(31)->withQueryString();

        $resumen = [];
        foreach ($partes as $parte) {
            $control = $this->almacen->controlDelDia($parte->fecha);
            $resumen[$parte->id] = [
                'ingreso_llenos' => $control['lleno_s10']['ingreso'] + $control['lleno_s45']['ingreso'] + $control['lleno_m10']['ingreso'],
                'salida_llenos' => $control['lleno_s10']['salida'] + $control['lleno_s45']['salida'] + $control['lleno_m10']['salida'],
                'final_s10' => AlmacenService::totalesPorPresentacion($control)['S10'],
                'final_s45' => AlmacenService::totalesPorPresentacion($control)['S45'],
                'final_m10' => AlmacenService::totalesPorPresentacion($control)['M10'],
                'vacios_s10' => AlmacenService::totalesVacios($control)['S10'],
                'vacios_s45' => AlmacenService::totalesVacios($control)['S45'],
            ];
        }

        $hoy = $this->resumen($request, fn () => [
            'control' => $this->almacen->controlDelDia(today()),
            'ultimo' => Parte::max('fecha'),
            'abiertos' => Parte::where('estado', Parte::ABIERTO)->count(),
        ]);

        return $this->tableOrPage($request, 'logistica.partes.index', 'logistica.partes._table', compact('partes', 'resumen', 'hoy'));
    }

    /** Abre el parte de una fecha (si no existe todavía, se crea al guardar). */
    public function show(string $fecha): View
    {
        $dia = Carbon::parse($fecha);
        $parte = Parte::with('filas')->firstOrNew(['fecha' => $dia->toDateString()], ['estado' => Parte::ABIERTO]);

        $instalaciones = Instalacion::activas()->with('empresa')->get();
        $config = [
            'fecha' => $dia->toDateString(),
            'editable' => $parte->esEditable(),
            'urls' => ['guardar' => route('logistica.partes.update', $dia->toDateString())],
            'inicial' => $this->almacen->stockAl($dia, false),
            'filas' => $parte->filas->map(fn (ParteFila $f) => $f->only([
                'bloque', 'placa', 'responsable', 'lugar', 'empresa_id', 'instalacion_id', 'numero_guia',
                's10', 's45', 'm10', 'cambio_s10', 'cambio_s45', 'cambio_m10', 'color_s10', 'color_s45', 'observacion',
            ]))->values(),
            'observaciones' => $parte->observaciones,
            'instalaciones' => $instalaciones->map(fn ($i) => ['id' => $i->id, 'empresa_id' => $i->empresa_id, 'codigo' => $i->codigo, 'placas' => $i->listaPlacas(), 'responsable' => $i->responsable])->values(),
        ];

        return view('logistica.partes.show', [
            'parte' => $parte,
            'dia' => $dia,
            'config' => $config,
            'choferes' => Chofer::activos()->pluck('alias'),
            'placas' => Vehiculo::orderBy('placa')->pluck('placa'),
            'empresas' => Empresa::activas()->pluck('nombre', 'id'),
            'instalaciones' => $instalaciones,
            'masa' => $parte->exists ? $this->almacen->controlDeMasa($parte) : collect(),
            'cuadre' => $this->almacen->cuadreChoferes($dia),
            'anterior' => Parte::where('fecha', '<', $dia->toDateString())->orderByDesc('fecha')->value('fecha'),
            'siguiente' => Parte::where('fecha', '>', $dia->toDateString())->orderBy('fecha')->value('fecha'),
        ]);
    }

    public function abrir(Request $request): RedirectResponse
    {
        $fecha = $request->date('fecha') ?? today();

        return redirect()->route('logistica.partes.show', $fecha->toDateString());
    }

    public function update(ParteRequest $request, string $fecha): JsonResponse
    {
        $parte = $this->almacen->guardar(Carbon::parse($fecha), $request->filasConDatos(), $request->observaciones);

        return $this->ok('Parte del '.$parte->fecha->format('d/m/Y').' guardado.', ['reloadPage' => true]);
    }

    public function cerrar(string $fecha): JsonResponse
    {
        $parte = Parte::where('fecha', Carbon::parse($fecha)->toDateString())->firstOrFail();
        $parte->update(['estado' => Parte::CERRADO, 'cerrado_por' => auth()->id(), 'cerrado_at' => now()]);
        AuditLogger::event('cerrada', 'Cerró el parte diario del '.$parte->fecha->format('d/m/Y'), $parte);

        return $this->ok('Parte cerrado. Ya no se puede modificar.', ['reloadPage' => true]);
    }

    public function reabrir(string $fecha): JsonResponse
    {
        $parte = Parte::where('fecha', Carbon::parse($fecha)->toDateString())->firstOrFail();
        $parte->update(['estado' => Parte::ABIERTO, 'cerrado_por' => null, 'cerrado_at' => null]);
        AuditLogger::event('reabierta', 'Reabrió el parte diario del '.$parte->fecha->format('d/m/Y'), $parte);

        return $this->ok('Parte reabierto.', ['reloadPage' => true]);
    }

    public function ajusteForm(string $fecha): View
    {
        $dia = Carbon::parse($fecha);

        return view('logistica.partes.ajuste', ['dia' => $dia, 'control' => $this->almacen->controlDelDia($dia)]);
    }

    public function ajuste(Request $request, string $fecha): JsonResponse
    {
        $data = $request->validate(['conteo' => ['required', 'array'], 'conteo.*' => ['nullable', 'integer', 'min:0']]);
        $parte = Parte::firstOrCreate(['fecha' => Carbon::parse($fecha)->toDateString()], ['estado' => Parte::ABIERTO, 'user_id' => auth()->id()]);
        abort_unless($parte->esEditable(), 422, 'El parte está cerrado.');
        $filas = $this->almacen->ajustarAConteo($parte, $data['conteo']);
        AuditLogger::event('ajuste', 'Ajustó el stock a conteo físico', $parte, $data['conteo']);

        return $this->ok($filas ? 'Stock ajustado al conteo físico.' : 'El conteo coincide con el stock; no se hicieron ajustes.', ['reloadPage' => true]);
    }

    /** Parte del día en PDF o Excel, con el mismo orden que la hoja de logística. */
    public function reporte(Request $request, string $fecha): Response
    {
        $dia = Carbon::parse($fecha);
        $parte = Parte::with(['filas.instalacion', 'filas.empresa'])->where('fecha', $dia->toDateString())->firstOrFail();
        $control = $this->almacen->controlDelDia($dia);
        $formato = $request->formato === 'xlsx' ? 'xlsx' : 'pdf';

        $reporte = new Reporte('Parte diario de almacén', ucfirst($dia->translatedFormat('l d \d\e F \d\e Y')), true);
        $reporte->datos(array_filter(['Estado' => $parte->esEditable() ? 'Abierto' : 'Cerrado', 'Registró' => $parte->user?->name, 'Cerró' => $parte->cerradoPor?->name]));

        $bloques = [
            ParteFila::LLENO_INGRESO => ['Ingreso de llenos', ParteFila::COLUMNAS_LLENOS, true],
            ParteFila::LLENO_SALIDA => ['Salida de llenos', ParteFila::COLUMNAS_LLENOS, false],
            ParteFila::VACIO_INGRESO => ['Ingreso de vacíos', ParteFila::COLUMNAS_VACIOS, false],
            ParteFila::VACIO_SALIDA => ['Salida de vacíos (a planta y canje)', ParteFila::COLUMNAS_VACIOS, true],
        ];
        foreach ($bloques as $bloque => [$titulo, $columnas, $conPlanta]) {
            $cols = ['Placa' => 'texto', 'Responsable' => 'texto', 'Lugar' => 'texto'];
            if ($conPlanta) {
                $cols += ['Instalación' => 'texto', 'Empresa' => 'texto', 'Guía' => 'texto'];
            }
            foreach ($columnas as $titCol) {
                $cols[$titCol] = 'entero';
            }
            $cols['Total'] = 'entero';
            $filas = $parte->filas->where('bloque', $bloque);
            $datos = $filas->map(function (ParteFila $f) use ($columnas, $conPlanta) {
                $fila = [$f->placa, $f->responsable, $f->lugar];
                if ($conPlanta) {
                    array_push($fila, $f->instalacion?->codigo, $f->empresa?->nombre, $f->numero_guia);
                }
                foreach (array_keys($columnas) as $c) {
                    $fila[] = $f->{$c};
                }
                $fila[] = collect(array_keys($columnas))->sum(fn ($c) => $f->{$c});

                return $fila;
            })->values();
            $total = array_merge(['TOTAL', '', ''], $conPlanta ? ['', '', ''] : [], array_map(fn ($c) => $filas->sum($c), array_keys($columnas)),
                [$filas->sum(fn ($f) => collect(array_keys($columnas))->sum(fn ($c) => $f->{$c}))]);
            $reporte->tabla($titulo, $cols, $datos, $total);

            if ($bloque === ParteFila::LLENO_SALIDA || $bloque === ParteFila::VACIO_SALIDA) {
                $llaves = $bloque === ParteFila::LLENO_SALIDA
                    ? ['lleno_s10', 'lleno_s45', 'lleno_m10', 'cambio_s10', 'cambio_s45', 'cambio_m10']
                    : ['plomo_s10', 'plomo_s45', 'color_s10', 'color_s45'];
                $colsControl = ['Control de stock' => 'texto'];
                foreach ($llaves as $l) {
                    $colsControl[$control[$l]['titulo']] = 'entero';
                }
                $reporte->tabla($bloque === ParteFila::LLENO_SALIDA ? 'Control de stock llenos' : 'Control de stock vacíos', $colsControl, [
                    array_merge(['Stock inicial'], array_map(fn ($l) => $control[$l]['inicial'], $llaves)),
                    array_merge(['(+) Ingreso'], array_map(fn ($l) => $control[$l]['ingreso'], $llaves)),
                    array_merge(['(−) Salida'], array_map(fn ($l) => $control[$l]['salida'], $llaves)),
                ], array_merge(['STOCK FINAL'], array_map(fn ($l) => $control[$l]['final'], $llaves)));
                if ($bloque === ParteFila::LLENO_SALIDA) {
                    $reporte->tabla('Total (llenos + cambios)', ['S-10' => 'entero', 'S-45' => 'entero', 'M-10' => 'entero'],
                        [array_values(AlmacenService::totalesPorPresentacion($control))]);
                } else {
                    $reporte->tabla('Total vacíos (plomos + colores)', ['S-10' => 'entero', 'S-45' => 'entero'],
                        [array_values(AlmacenService::totalesVacios($control))]);
                }
            }
        }

        $masa = $this->almacen->controlDeMasa($parte);
        $reporte->tabla('Control de masa con planta', ['Placa' => 'texto', 'Responsable' => 'texto', 'Empresa' => 'texto', 'Guías' => 'texto', 'Vacíos a planta' => 'entero', 'Llenos de planta' => 'entero', 'Diferencia' => 'entero'],
            $masa->map(fn ($m) => [$m['placa'], $m['responsable'], $m['empresa'], $m['guias'], $m['vacios'], $m['llenos'], $m['diferencia']]), null,
            $parte->observaciones ? 'Observaciones: '.$parte->observaciones : null);

        return $reporte->descargar($formato, 'parte-'.$dia->toDateString());
    }
}
