<x-layouts.app title="Consolidado de vales FISE" breadcrumb="Reportes">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="form-label">Mes</label>
                <input type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="form-input w-56" onchange="this.form.submit()">
            </div>
        </form>
        <x-export :url="route('reportes.fise', ['mes' => $mes->format('Y-m')])"/>
    </x-slot:actions>

    <dl class="ledger !grid-cols-2 lg:!grid-cols-5">
        <x-cifra label="Periodo" :value="ucfirst($mes->translatedFormat('F Y'))"/>
        @foreach ($valores as $v)
            <x-cifra :label="'Vales de S/ '.$v" :value="num($cantidad(null, $v))" :hint="soles($v * $cantidad(null, $v))"/>
        @endforeach
        <x-cifra label="Importe total FISE" :value="soles($total)" :hint="$responsables->count().' responsable(s)'" total/>
    </dl>

    <section class="card mt-4">
        <div class="card-header">
            <p class="card-title">Cantidad de vales por día y responsable</p>
            <span class="text-[12px] text-slate-500">Según fecha de venta de cada liquidación</span>
        </div>
        @if ($responsables->isEmpty())
            <x-empty text="No hay vales FISE registrados en el mes."/>
        @else
            <div class="table-wrap">
                <table class="table table-grid table-compact">
                    <thead>
                    <tr class="th-group">
                        <th rowspan="2" class="!bg-head !text-left align-bottom !text-slate-700">Fecha</th>
                        @foreach ($responsables as $resp)<th colspan="{{ count($valores) }}">{{ $resp }}</th>@endforeach
                        <th rowspan="2" class="!bg-head align-bottom !text-right !text-slate-700">Importe (S/)</th>
                    </tr>
                    <tr>
                        @foreach ($responsables as $resp)
                            @foreach ($valores as $v)<th class="text-right">{{ $v }}</th>@endforeach
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($dias as $d)
                        @php($f = $d->toDateString())
                        <tr @class(['text-slate-400' => ! isset($celdas[$f]), 'bg-panel' => $d->isSunday()])>
                            <td class="whitespace-nowrap font-medium {{ isset($celdas[$f]) ? 'text-slate-900' : '' }}">{{ $d->format('d') }}-{{ mb_strtolower($d->translatedFormat('M')) }}</td>
                            @foreach ($responsables as $resp)
                                @foreach ($valores as $v)
                                    <td class="text-right">{{ $celdas[$f][$resp][$v] ?? '' }}</td>
                                @endforeach
                            @endforeach
                            <td class="text-right font-medium">{{ isset($celdas[$f]) ? num($importeDia($f), 2) : '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>Total vales</td>
                        @foreach ($responsables as $resp)
                            @foreach ($valores as $v)<td class="text-right">{{ $cantidad($resp, $v) ?: '' }}</td>@endforeach
                        @endforeach
                        <td class="text-right">{{ num($total, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Importe (S/)</td>
                        @foreach ($responsables as $resp)<td colspan="{{ count($valores) }}" class="text-center">{{ num($importeResp($resp), 2) }}</td>@endforeach
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
