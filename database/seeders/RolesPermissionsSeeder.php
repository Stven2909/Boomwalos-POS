<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'crear_pedido',
            'cobrar_pedido',
            'operar_cocina',
            'cancelar_pedido',
            'aplicar_descuento',
            'gestionar_productos',
            'gestionar_combos',
            'gestionar_mesas',
            'gestionar_notas_cocina',
            'gestionar_usuarios',
            'gestionar_establecimientos',
            'gestionar_marca',
            'abrir_caja',
            'cerrar_caja',
            'ver_reportes',
            'gestionar_solicitudes_fiscales',
            'gestionar_configuracion_fiscal',
            'ver_ventas_fiscales',
            'reintentar_sincronizacion_fiscal',
            'solicitar_documento_fiscal',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $administrador = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);

        $administrador->syncPermissions([
            'crear_pedido',
            'cobrar_pedido',
            'operar_cocina',
            'cancelar_pedido',
            'gestionar_productos',
            'gestionar_combos',
            'gestionar_mesas',
            'gestionar_notas_cocina',
            'gestionar_usuarios',
            'gestionar_establecimientos',
            'gestionar_marca',
            'abrir_caja',
            'cerrar_caja',
            'ver_reportes',
            'gestionar_solicitudes_fiscales',
            'gestionar_configuracion_fiscal',
            'ver_ventas_fiscales',
            'reintentar_sincronizacion_fiscal',
            'solicitar_documento_fiscal',
        ]);

        $cajero->syncPermissions([
            'crear_pedido',
            'cobrar_pedido',
            'operar_cocina',
            'abrir_caja',
            'cerrar_caja',
        ]);

        // aplicar_descuento queda creado como permiso existente pero SIN asignar,
        // a la espera de la decisión del negocio.
        // cancelar_pedido se asignó al administrador para anular pedidos completos.
    }
}
