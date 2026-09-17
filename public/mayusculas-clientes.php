<?php

/**
 * Pasa a MAYÚSCULAS los textos de clientes existentes (excepto email).
 * Uso único en cPanel:
 * 1. Sube este archivo a public/
 * 2. Ábrelo en el navegador
 * 3. BORRA el archivo
 */

use App\Models\Customer;

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$fields = Customer::UPPERCASE_FIELDS;
$updated = 0;
$scanned = 0;

try {
    Customer::query()
        ->withTrashed()
        ->orderBy('id')
        ->chunkById(100, function ($customers) use ($fields, &$updated, &$scanned): void {
            foreach ($customers as $customer) {
                $scanned++;
                $dirty = false;

                foreach ($fields as $field) {
                    $value = $customer->getAttributes()[$field] ?? null;

                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $upper = mb_strtoupper(trim($value), 'UTF-8');

                    if ($upper !== $value) {
                        $customer->setAttribute($field, $upper);
                        $dirty = true;
                    }
                }

                if (isset($customer->getAttributes()['email']) && is_string($customer->email)) {
                    $email = trim($customer->email);
                    if ($email !== $customer->email) {
                        $customer->email = $email;
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    // Evita re-disparar lógica extra; normalizeTextCase corre en saving.
                    $customer->save();
                    $updated++;
                }
            }
        });

    echo "OK: revisados={$scanned}, actualizados={$updated}\n";
    echo 'Campos: '.implode(', ', $fields)."\n";
    echo "BORRA este archivo ahora (mayusculas-clientes.php).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: '.$e->getMessage()."\n";
}
