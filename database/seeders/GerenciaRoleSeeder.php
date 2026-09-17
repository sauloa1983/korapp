<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** @deprecated Usa SyncCommercialRolesSeeder */
class GerenciaRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SyncCommercialRolesSeeder::class);
    }
}
