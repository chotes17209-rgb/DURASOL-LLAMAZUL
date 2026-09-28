<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductoRequest;
use App\Models\LiquidacionItem;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $productos = Producto::with('envase')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->whereLike('codigo', "%$t%")->orWhereLike('nombre', "%$t%")))
            ->orderBy('orden')->orderBy('codigo')->paginate(30)->withQueryString();

        return $this->tableOrPage($request, 'admin.productos.index', 'admin.productos._table', compact('productos'));
    }

    public function create(): View
    {
        return $this->form(new Producto(['activo' => true, 'controla_stock' => true, 'tipo' => Producto::TIPO_GAS]));
    }

    public function store(ProductoRequest $request): JsonResponse
    {
        $producto = Producto::create($request->validated());

        return $this->ok("Producto {$producto->codigo} registrado.");
    }

    public function show(Producto $producto): View
    {
        return view('admin.productos.show', compact('producto'));
    }

    public function edit(Producto $producto): View
    {
        return $this->form($producto);
    }

    public function update(ProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto->update($request->validated());

        return $this->ok("Producto {$producto->codigo} actualizado.");
    }

    public function destroy(Producto $producto): JsonResponse
    {
        $usado = LiquidacionItem::where('producto_id', $producto->id)->exists();
        abort_if($usado, 422, 'El producto ya tiene movimientos o ventas. Desactívalo en lugar de eliminarlo.');
        $producto->delete();

        return $this->ok('Producto eliminado.');
    }

    private function form(Producto $producto): View
    {
        $envases = Producto::orderBy('codigo')->pluck('codigo', 'id');

        return view('admin.productos.form', compact('producto', 'envases'));
    }
}
