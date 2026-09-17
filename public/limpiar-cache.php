<?php

/**
 * Limpia cachés de Laravel en cPanel (sin Terminal).
 * Ábrelo una vez y BORRA el archivo.
 */

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

try {
    foreach (['config', 'route', 'view', 'event'] as $type) {
        try {
            Illuminate\Support\Facades\Artisan::call($type.':clear');
            echo "OK: {$type}:clear\n";
        } catch (Throwable $e) {
            echo "WARN {$type}:clear — {$e->getMessage()}\n";
        }
    }

    try {
        Illuminate\Support\Facades\Artisan::call('optimize:clear');
        echo "OK: optimize:clear\n";
    } catch (Throwable $e) {
        echo 'WARN optimize:clear — '.$e->getMessage()."\n";
    }

    echo "Listo. BORRA limpiar-cache.php ahora.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: '.$e->getMessage()."\n";
}
