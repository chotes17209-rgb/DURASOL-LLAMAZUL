<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\User;

/**
 * Menú lateral según el rol del usuario.
 */
class Menu
{
    /** @return array<int, array{titulo: string, items: array<int, array>}> */
    public static function para(User $user): array
    {
        $secciones = [
            ['titulo' => 'General', 'items' => [
                ['label' => 'Panel de control', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'squares-2x2', 'roles' => []],
            ]],
            ['titulo' => 'Logística', 'items' => [
                ['label' => 'Parte diario', 'route' => 'logistica.partes.index', 'match' => 'logistica.partes.*', 'icon' => 'clipboard-document-list', 'roles' => [Rol::Logistica]],
                ['label' => 'Stock y kardex', 'route' => 'logistica.stock', 'match' => 'logistica.stock*', 'icon' => 'cube', 'roles' => [Rol::Logistica, Rol::Liquidaciones]],
            ]],
            ['titulo' => 'Ventas', 'items' => [
                ['label' => 'Liquidaciones', 'route' => 'liquidaciones.index', 'match' => ['liquidaciones.*'], 'icon' => 'clipboard-document-check', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
                ['label' => 'Clientes', 'route' => 'clientes.index', 'match' => 'clientes.*', 'icon' => 'users', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
                ['label' => 'Créditos y cobranzas', 'route' => 'creditos.index', 'match' => 'creditos.*', 'icon' => 'credit-card', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
            ]],
            ['titulo' => 'Precios', 'items' => [
                ['label' => 'Precios de compra', 'route' => 'precios.compra.index', 'match' => 'precios.compra.*', 'icon' => 'building-storefront', 'roles' => [Rol::Logistica]],
                ['label' => 'Precios de venta', 'route' => 'precios.venta.index', 'match' => 'precios.venta.*', 'icon' => 'tag', 'roles' => [Rol::Liquidaciones]],
            ]],
            ['titulo' => 'Caja', 'items' => [
                ['label' => 'Caja general', 'route' => 'caja.index', 'match' => ['caja.index', 'caja.movimientos.*'], 'icon' => 'banknotes', 'roles' => [Rol::Caja]],
                ['label' => 'Caja chica', 'route' => 'caja.chica.index', 'match' => 'caja.chica.*', 'icon' => 'wallet', 'roles' => [Rol::Caja]],
                ['label' => 'Arqueo de efectivo', 'route' => 'caja.arqueos.index', 'match' => 'caja.arqueos.*', 'icon' => 'calculator', 'roles' => [Rol::Caja]],
                ['label' => 'Depósitos', 'route' => 'caja.depositos.index', 'match' => 'caja.depositos.*', 'icon' => 'building-library', 'roles' => [Rol::Caja]],
            ]],
            ['titulo' => 'Gerencia', 'items' => [
                ['label' => 'Rentabilidad', 'route' => 'reportes.rentabilidad', 'match' => 'reportes.rentabilidad', 'icon' => 'presentation-chart-line', 'roles' => [Rol::Admin]],
            ]],
            ['titulo' => 'Reportes', 'items' => [
                ['label' => 'Liquidación diaria', 'route' => 'reportes.liquidacion-diaria', 'match' => 'reportes.liquidacion-diaria', 'icon' => 'document-chart-bar', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
                ['label' => 'Detalle de ventas', 'route' => 'reportes.ventas', 'match' => 'reportes.ventas', 'icon' => 'table-cells', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
                ['label' => 'Caja por día', 'route' => 'reportes.caja-diaria', 'match' => 'reportes.caja-diaria', 'icon' => 'calendar-days', 'roles' => [Rol::Caja]],
                ['label' => 'Consolidado FISE', 'route' => 'reportes.fise', 'match' => 'reportes.fise', 'icon' => 'ticket', 'roles' => [Rol::Liquidaciones, Rol::Caja]],
            ]],
            ['titulo' => 'Flota y personal', 'items' => [
                ['label' => 'Vehículos', 'route' => 'vehiculos.index', 'match' => ['vehiculos.*', 'documentos.*', 'mantenimientos.*'], 'icon' => 'truck', 'roles' => [Rol::Logistica]],
                ['label' => 'Choferes', 'route' => 'choferes.index', 'match' => 'choferes.*', 'icon' => 'identification', 'roles' => [Rol::Logistica]],
                ['label' => 'Instalaciones Solgas', 'route' => 'instalaciones.index', 'match' => 'instalaciones.*', 'icon' => 'map-pin', 'roles' => [Rol::Logistica]],
            ]],
            ['titulo' => 'Administración', 'items' => [
                ['label' => 'Empresas', 'route' => 'empresas.index', 'match' => 'empresas.*', 'icon' => 'briefcase', 'roles' => [Rol::Admin]],
                ['label' => 'Productos', 'route' => 'productos.index', 'match' => 'productos.*', 'icon' => 'fire', 'roles' => [Rol::Admin]],
                ['label' => 'Cuentas bancarias', 'route' => 'cuentas-bancarias.index', 'match' => 'cuentas-bancarias.*', 'icon' => 'building-library', 'roles' => [Rol::Caja]],
                ['label' => 'Usuarios', 'route' => 'usuarios.index', 'match' => 'usuarios.*', 'icon' => 'user-group', 'roles' => [Rol::Admin]],
                ['label' => 'Historial', 'route' => 'historial.index', 'match' => 'historial.*', 'icon' => 'clock', 'roles' => [Rol::Admin]],
            ]],
        ];

        $resultado = [];
        foreach ($secciones as $seccion) {
            $items = array_values(array_filter(
                $seccion['items'],
                fn ($item) => $item['roles'] === [] || $user->hasRole(...$item['roles'])
            ));
            if ($items !== []) {
                $resultado[] = ['titulo' => $seccion['titulo'], 'items' => $items];
            }
        }

        return $resultado;
    }
}
