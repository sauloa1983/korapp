<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'sauloandres@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'pin' => '1234',
            ],
        );

        $operarioUser = User::updateOrCreate(
            ['email' => 'operario@korapp.test'],
            [
                'name' => 'Operario Demo',
                'password' => Hash::make('password'),
                'pin' => '1111',
            ],
        );

        $this->seedRoles($admin, $operarioUser);

        $this->call(KorappSeeder::class);
        $this->call(CrmDemoSeeder::class);
        $this->call(AcrylicQuoteSeeder::class);
        $this->call(Serna2026PriceListSeeder::class);
    }

    /**
     * Roles y permisos (Shield / spatie-permission).
     * super_admin: acceso total vía Gate (define_via_gate).
     */
    private function seedRoles(User $admin, User $operarioUser): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->syncRoles([$superAdmin]);

        $operarioPermissionNames = [
            'ViewAny:ProductionOrder', 'View:ProductionOrder',
            'ViewAny:Process', 'View:Process',
            'ViewAny:Item', 'View:Item',
            'View:EscaneoOperario',
        ];
        $this->ensurePermissions($operarioPermissionNames);

        $operario = Role::firstOrCreate(['name' => 'Operario', 'guard_name' => 'web']);
        $operario->syncPermissions(
            Permission::query()->whereIn('name', $operarioPermissionNames)->get()
        );
        $operarioUser->syncRoles([$operario]);

        $vendedorPermissionNames = [
            // Clientes
            'ViewAny:Customer', 'View:Customer', 'Create:Customer', 'Update:Customer',
            // Ventas
            'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'Update:Sale',
            'View:PuntoDeVenta',
            // Inventario (consulta)
            'ViewAny:Item', 'View:Item',
            // CRM prospectos / cotizaciones / agenda
            'ViewAny:Lead', 'View:Lead', 'Create:Lead', 'Update:Lead', 'Delete:Lead',
            'ViewAny:Quote', 'View:Quote', 'Create:Quote', 'Update:Quote', 'Delete:Quote',
            'ViewAny:Visit', 'View:Visit', 'Create:Visit', 'Update:Visit', 'Delete:Visit',
            // Páginas CRM
            'View:SalesDashboard',
            'View:SalesPipeline',
            'View:CotizadorAcrilico',
            // Parámetros cotizador acrílico (consulta / ajuste comercial)
            'ViewAny:AcrylicMaterial', 'View:AcrylicMaterial',
            'ViewAny:AcrylicLightingOption', 'View:AcrylicLightingOption',
            'ViewAny:AcrylicFinishOption', 'View:AcrylicFinishOption',
            'ViewAny:AcrylicLetteringOption', 'View:AcrylicLetteringOption',
        ];
        $this->ensurePermissions($vendedorPermissionNames);

        // Solo administración (no se asignan a Vendedor).
        $this->ensurePermissions([
            'View:ReasignarClientes',
            'View:Reportes',
            'View:ConfiguracionEmpresa',
        ]);

        $vendedor = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions(
            Permission::query()->whereIn('name', $vendedorPermissionNames)->get()
        );

        User::updateOrCreate(
            ['email' => 'vendedor@korapp.test'],
            [
                'name' => 'Vendedor Demo',
                'password' => Hash::make('password'),
                'pin' => '2222',
            ],
        )->syncRoles([$vendedor]);
    }

    /** @param  list<string>  $names */
    private function ensurePermissions(array $names): void
    {
        foreach ($names as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }
    }
}
