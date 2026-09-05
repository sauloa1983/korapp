<?php

/**
 * Uso único en cPanel (sin Terminal):
 * 1. Sube este archivo a public_html/korapp/public/sync-operario.php
 * 2. Ábrelo en el navegador (logueado no es necesario)
 * 3. BORRA el archivo al terminar
 */

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

try {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissionNames = [
        'ViewAny:ProductionOrder',
        'View:ProductionOrder',
        'View:EscaneoOperario',
    ];

    foreach ($permissionNames as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'Operario', 'guard_name' => 'web']);
    $role->syncPermissions(
        Permission::query()->whereIn('name', $permissionNames)->get()
    );

    $user = User::query()->where('email', 'operario@korapp.test')->first();
    if ($user) {
        $user->syncRoles([$role]);
        echo "OK: rol Operario sincronizado para {$user->email}\n";
    } else {
        echo "OK: rol Operario sincronizado (usuario operario@korapp.test no encontrado)\n";
    }

    echo 'Permisos: '.implode(', ', $permissionNames)."\n";
    echo "BORRA este archivo ahora (sync-operario.php).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: '.$e->getMessage()."\n";
}
