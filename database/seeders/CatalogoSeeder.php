<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\CuentaBancaria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos base: usuarios por rol, las dos empresas, productos y cuentas bancarias.
 */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'demo1234');
        $usuarios = [
            ['name' => 'Gerencia', 'username' => 'admin', 'email' => 'admin@durasol.pe', 'role' => Rol::Admin],
            ['name' => 'Área Logística', 'username' => 'logistica', 'email' => 'logistica@durasol.pe', 'role' => Rol::Logistica],
            ['name' => 'Caja', 'username' => 'caja', 'email' => 'caja@durasol.pe', 'role' => Rol::Caja],
            ['name' => 'Liquidaciones y Ventas', 'username' => 'liquidaciones', 'email' => 'liquidaciones@durasol.pe', 'role' => Rol::Liquidaciones],
        ];
        foreach ($usuarios as $u) {
            User::updateOrCreate(['username' => $u['username']], $u + ['password' => $password, 'active' => true]);
        }

        Empresa::updateOrCreate(['nombre' => 'DURASOL'], ['razon_social' => 'MR. DURASOL PERÚ S.A.C.', 'color' => '#1e3a8a', 'activo' => true]);
        Empresa::updateOrCreate(['nombre' => 'LLAMAZUL'], ['razon_social' => 'LLAMAZUL', 'color' => '#0ea5e9', 'activo' => true]);

        // codigo, nombre, marca, kg, tipo, planta, stock, orden
        $productos = [
            ['S10', 'Solgas 10 kg', 'SOLGAS', 10, 'gas', true, true, 1],
            ['S45', 'Solgas 45 kg', 'SOLGAS', 45, 'gas', true, true, 2],
            ['M10', 'Masgas 10 kg', 'MASGAS', 10, 'gas', true, true, 3],
            ['C10', 'Contigas 10 kg', 'CONTIGAS', 10, 'gas', false, true, 4],
            ['C45', 'Contigas 45 kg', 'CONTIGAS', 45, 'gas', false, true, 5],
            ['K10', 'Balón vacío 10 kg (venta de envase)', null, 10, 'envase', false, true, 6],
            ['K45', 'Balón vacío 45 kg (venta de envase)', null, 45, 'envase', false, true, 7],
            ['REG', 'Regulador', null, null, 'accesorio', false, false, 8],
        ];
        foreach ($productos as [$codigo, $nombre, $marca, $kg, $tipo, $planta, $stock, $orden]) {
            Producto::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre, 'marca' => $marca, 'capacidad_kg' => $kg, 'tipo' => $tipo,
                'se_compra_en_planta' => $planta, 'controla_stock' => $stock, 'orden' => $orden, 'activo' => true,
            ]);
        }
        // Envase vacío que corresponde a cada producto (10 kg → S10, 45 kg → S45).
        $envase10 = Producto::where('codigo', 'S10')->value('id');
        $envase45 = Producto::where('codigo', 'S45')->value('id');
        Producto::whereIn('codigo', ['S10', 'M10', 'C10', 'K10'])->update(['envase_id' => $envase10]);
        Producto::whereIn('codigo', ['S45', 'C45', 'K45'])->update(['envase_id' => $envase45]);

        $durasol = Empresa::where('nombre', 'DURASOL')->value('id');
        $llamazul = Empresa::where('nombre', 'LLAMAZUL')->value('id');
        $cuentas = [
            ['banco' => 'BCP', 'alias' => 'DURASOL', 'empresa_id' => $durasol],
            ['banco' => 'BCP', 'alias' => 'LLAMAZUL', 'empresa_id' => $llamazul],
            ['banco' => 'YAPE', 'alias' => 'TRANSPORTES', 'empresa_id' => null],
        ];
        foreach ($cuentas as $c) {
            CuentaBancaria::updateOrCreate(['banco' => $c['banco'], 'alias' => $c['alias']], $c + ['moneda' => 'PEN', 'activo' => true]);
        }
    }
}
