<?php

/**
 * Resincroniza permisos de Operario, Vendedor y Gerencia (cPanel sin Terminal).
 * Ábrelo una vez y BORRA el archivo.
 */

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

try {
    Illuminate\Support\Facades\Artisan::call('db:seed', [
        '--class' => 'Database\\Seeders\\SyncCommercialRolesSeeder',
        '--force' => true,
    ]);

    echo "OK: SyncCommercialRolesSeeder\n";
    echo Illuminate\Support\Facades\Artisan::output();
    echo "Listo. BORRA sincronizar-roles.php ahora.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: '.$e->getMessage()."\n";
}
