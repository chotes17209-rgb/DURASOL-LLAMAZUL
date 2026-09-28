<?php

namespace Tests;

use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Instalacion;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(CatalogoSeeder::class);
    }

    protected function como(string $username = 'admin'): static
    {
        return $this->actingAs(User::where('username', $username)->firstOrFail());
    }

    protected function producto(string $codigo): Producto
    {
        return Producto::where('codigo', $codigo)->firstOrFail();
    }

    protected function empresa(string $nombre = 'DURASOL'): Empresa
    {
        return Empresa::where('nombre', $nombre)->firstOrFail();
    }

    protected function chofer(string $alias = 'URBANO', string $tipo = 'local'): Chofer
    {
        return Chofer::firstOrCreate(['alias' => $alias], ['tipo' => $tipo, 'activo' => true]);
    }

    protected function cliente(array $precios = ['S10' => 45], string $nombre = 'BODEGA ROSA'): Cliente
    {
        $cliente = Cliente::create(['codigo' => Cliente::max('codigo') + 1, 'nombre' => $nombre, 'tipo' => 'local', 'activo' => true, 'chofer_id' => $this->chofer()->id]);
        foreach ($precios as $codigo => $precio) {
            $cliente->preciosVenta()->create(['producto_id' => $this->producto($codigo)->id, 'precio' => $precio, 'vigente_desde' => '2026-01-01']);
        }

        return $cliente;
    }

    protected function instalacion(string $empresa = 'DURASOL', string $codigo = '12345678'): Instalacion
    {
        return Instalacion::create(['codigo' => $codigo, 'nombre' => "Planta {$empresa}", 'empresa_id' => $this->empresa($empresa)->id, 'activo' => true]);
    }
}
