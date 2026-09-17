<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Resincroniza permisos de Operario, Vendedor y Gerencia
 * (sin tocar super_admin ni usuarios).
 */
class SyncCommercialRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $operario = [
            'ViewAny:ProductionOrder', 'View:ProductionOrder',
            'View:EscaneoOperario',
        ];

        $vendedor = [
            'ViewAny:Customer', 'View:Customer', 'Create:Customer', 'Update:Customer',
            'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'Update:Sale',
            'View:PuntoDeVenta',
            'ViewAny:Item', 'View:Item',
            'ViewAny:Lead', 'View:Lead', 'Create:Lead', 'Update:Lead', 'Delete:Lead',
            'ViewAny:Quote', 'View:Quote', 'Create:Quote', 'Update:Quote', 'Delete:Quote',
            'ViewAny:Visit', 'View:Visit', 'Create:Visit', 'Update:Visit', 'Delete:Visit',
            'View:SalesDashboard',
            'View:SalesPipeline',
            'View:CotizadorAcrilico',
            'View:Entregas',
        ];

        $gerencia = array_values(array_unique([
            ...$vendedor,
            'Import:Customer',
            'ViewAny:ProductionOrder',
            'View:ProductionOrder',
            'Update:ProductionOrder',
            'View:Reportes',
            'View:ReasignarClientes',
            'View:Entregas',
        ]));

        foreach (array_merge($operario, $vendedor, $gerencia, [
            'Import:Customer',
            'View:ReasignarClientes',
            'View:Reportes',
            'View:ConfiguracionEmpresa',
            'View:Entregas',
            'Reorder:Process',
        ]) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Reordenar solo aplica a Procesos (arrastrar filas). Limpia el resto.
        Permission::query()
            ->where('name', 'like', 'Reorder:%')
            ->where('name', '!=', 'Reorder:Process')
            ->delete();

        Role::firstOrCreate(['name' => 'Operario', 'guard_name' => 'web'])
            ->syncPermissions(Permission::query()->whereIn('name', $operario)->get());

        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web'])
            ->syncPermissions(Permission::query()->whereIn('name', $vendedor)->get());

        Role::firstOrCreate(['name' => 'Gerencia', 'guard_name' => 'web'])
            ->syncPermissions(Permission::query()->whereIn('name', $gerencia)->get());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
