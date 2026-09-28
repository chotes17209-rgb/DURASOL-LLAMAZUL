<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogoSeeder::class);

        // Datos reales del Excel RENTABILIDAD SETIEMBRE (clientes, precios, ventas, fises, stock...).
        if (env('SEED_EXCEL', true)) {
            $this->call(ExcelSeeder::class);
        }
    }
}
