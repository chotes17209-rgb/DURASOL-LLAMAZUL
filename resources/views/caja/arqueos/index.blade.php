@php
    $denominaciones = config('erp.denominaciones');
    $clave = fn ($v) => \App\Services\CajaChicaService::clave($v);
    $cantidades = [];
    foreach ($denominaciones as $valores) {
        foreach ($valores as $v) {
            $cantidades[$clave($v)] = ($arqueo?->detalle[$clave($v)] ?? 0) ?: '';
        }
    }
@endphp
<x-layouts.app title="Arqueo de efectivo" breadcrumb="Caja">
    <x-slot:filters>
        <form method="GET">
            <div class="fb">
                <label>Caja</label>
                <select name="caja" class="form-input w-44" onchange="this.form.submit()">
                    @foreach (\App\Models\Arqueo::CAJAS as $valor => $texto)<option value="{{ $valor }}" @selected($caja === $valor)>{{ $texto }}</option>@endforeach
                </select>
            </div>
            <div class="fb">
                <label>Fecha del arqueo</label>
                <input type="date" name="fecha" class="form-input w-44" value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            <button class="btn btn-primary">Aplicar</button>
        </form>
    </x-slot:filters>

    @include('caja._tabs')

    <form method="POST" action="{{ route('caja.arqueos.store') }}" data-ajax autocomplete="off"
          x-data="{
              c: @js($cantidades),
              billetes: @js($denominaciones['billetes']),
              monedas: @js($denominaciones['monedas']),
              sistema: {{ $saldoSistema }},
              k(v) { return Number(v).toFixed(2) },
              imp(v) { return Math.round((+this.c[this.k(v)] || 0) * v * 100) / 100 },
              sub(lista) { return Math.round(lista.reduce((s, v) => s + this.imp(v), 0) * 100) / 100 },
              get total() { return Math.round((this.sub(this.billetes) + this.sub(this.monedas)) * 100) / 100 },
              get diferencia() { return Math.round((this.total - this.sistema) * 100) / 100 },
              f(n) { return Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
          }">
        @csrf
        <input type="hidden" name="fecha" value="{{ $fecha->format('Y-m-d') }}">
        <input type="hidden" name="caja" value="{{ $caja }}">

        <section class="doc-head">
            <div class="doc-band">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-[12px] text-slate-500">Arqueo de efectivo · {{ \App\Models\Arqueo::CAJAS[$caja] }}</p>
                        <p class="doc-num">{{ $fecha->format('d/m/Y') }}</p>
                    </div>
                    <span class="doc-tag">{{ $arqueo ? 'Registrado' : 'Sin registrar' }}</span>
                </div>
                <div class="text-right text-[12px] leading-snug text-slate-600">
                    @if ($arqueo)
                        <p>Registrado por <b class="text-slate-900">{{ $arqueo->user?->name ?? '—' }}</b></p>
                        <p>{{ $arqueo->updated_at->format('d/m/Y H:i') }}</p>
                    @else
                        <p>Ingrese la cantidad de billetes y monedas contados.</p>
                    @endif
                </div>
            </div>
        </section>

        <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_22rem]">
            @foreach (['billetes' => 'Billetes', 'monedas' => 'Monedas'] as $tipo => $titulo)
                <section class="card">
                    <div class="card-header"><p class="card-title">{{ $titulo }}</p></div>
                    <table class="table table-grid">
                        <thead><tr><th>Denominación</th><th class="w-32 text-right">Cantidad</th><th class="w-36 text-right">Importe (S/)</th></tr></thead>
                        <tbody>
                        @foreach ($denominaciones[$tipo] as $v)
                            <tr>
                                <td class="font-medium text-slate-900">S/ {{ number_format($v, 2) }}</td>
                                <td class="!p-0"><input type="number" min="0" step="1" class="cell-input font-semibold" name="cantidades[{{ $clave($v) }}]" x-model="c['{{ $clave($v) }}']"></td>
                                <td class="text-right" x-text="imp({{ $v }}) ? f(imp({{ $v }})) : ''"></td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="2">Total {{ mb_strtolower($titulo) }}</td><td class="text-right" x-text="f(sub({{ $tipo }}))"></td></tr></tfoot>
                    </table>
                </section>
            @endforeach

            <section class="card self-start">
                <div class="card-header"><p class="card-title">Cuadre de efectivo</p></div>
                <table class="recibo">
                    <tr><td>Total billetes</td><td x-text="f(sub(billetes))"></td></tr>
                    <tr><td>Total monedas</td><td x-text="f(sub(monedas))"></td></tr>
                    <tr class="subtotal"><td>Total contado</td><td x-text="f(total)"></td></tr>
                    <tr><td>Saldo según sistema <span class="text-xs text-slate-400">al {{ $fecha->format('d/m') }}</span></td><td x-text="f(sistema)"></td></tr>
                    <tr class="final">
                        <td x-text="Math.abs(diferencia) < 0.005 ? 'Cuadra' : (diferencia > 0 ? 'Sobrante' : 'Faltante')"></td>
                        <td :class="Math.abs(diferencia) >= 0.005 && (diferencia > 0 ? '!text-emerald-800' : '!text-red-700')" x-text="'S/ ' + f(Math.abs(diferencia))"></td>
                    </tr>
                </table>
                <div class="space-y-3 border-t border-line p-4">
                    <div>
                        <label class="form-label">Observaciones</label>
                        <input name="observaciones" class="form-input" value="{{ $arqueo?->observaciones }}" placeholder="Opcional">
                    </div>
                    <button type="submit" class="btn btn-primary w-full">{{ $arqueo ? 'Actualizar arqueo' : 'Registrar arqueo' }}</button>
                    @if ($arqueo)
                        <div class="flex gap-2">
                            <a href="{{ route('caja.arqueos.show', [$arqueo, 'formato' => 'pdf']) }}" class="btn btn-secondary flex-1"><x-heroicon-o-document-arrow-down/> PDF</a>
                            <a href="{{ route('caja.arqueos.show', [$arqueo, 'formato' => 'xlsx']) }}" class="btn btn-secondary flex-1"><x-heroicon-o-table-cells/> Excel</a>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </form>

    <section class="card mt-4">
        <div class="card-header"><p class="card-title">Arqueos registrados</p><span class="text-[12px] text-slate-500">Últimos 30</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Fecha</th><th>Caja</th><th class="text-right">Total contado</th><th class="text-right">Saldo sistema</th><th class="text-right">Diferencia</th><th>Observaciones</th><th>Registró</th><th class="w-24"></th></tr></thead>
                <tbody>
                @forelse ($historial as $a)
                    @php($d = (float) $a->diferencia)
                    <tr @class(['bg-brand-50' => $a->fecha->equalTo($fecha) && $a->caja === $caja])>
                        <td><a href="{{ route('caja.arqueos.index', ['fecha' => $a->fecha->format('Y-m-d'), 'caja' => $a->caja]) }}" class="font-medium text-brand-800 hover:underline">{{ fecha($a->fecha) }}</a></td>
                        <td>{{ $a->nombreCaja() }}</td>
                        <td class="text-right">{{ num($a->total_contado, 2) }}</td>
                        <td class="text-right">{{ num($a->saldo_sistema, 2) }}</td>
                        <td class="text-right font-semibold {{ abs($d) < 0.005 ? 'text-slate-700' : ($d > 0 ? 'text-emerald-700' : 'text-red-700') }}">{{ abs($d) < 0.005 ? 'Cuadra' : ($d > 0 ? '+' : '−').num(abs($d), 2) }}</td>
                        <td class="text-[12px] text-slate-600">{{ $a->observaciones }}</td>
                        <td class="text-[12px]">{{ $a->user?->name }}</td>
                        <td><x-row-actions size="md" :show="route('caja.arqueos.show', $a)" :delete="route('caja.arqueos.destroy', $a)"/></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty text="Aún no hay arqueos registrados."/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
