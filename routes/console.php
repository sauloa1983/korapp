<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revisión diaria de inventario para alertar stock bajo a los administradores.
Schedule::command('inventory:check-low-stock')->dailyAt('08:00');
