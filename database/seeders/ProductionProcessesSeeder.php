<?php

namespace Database\Seeders;

use App\Enums\ProcessDepartment;
use App\Models\Process;
use Illuminate\Database\Seeder;

/** Solo procesos de la OP física (seguro de reejecutar). */
class ProductionProcessesSeeder extends Seeder
{
    public function run(): void
    {
        $processes = [
            ['slug' => 'impresion-plotter-de-corte', 'name' => 'Plotter de corte', 'department' => ProcessDepartment::Impresion, 'sort_order' => 10, 'estimated_minutes' => 20, 'color' => 'info'],
            ['slug' => 'impresion-impresion-rigidos', 'name' => 'Impresión rígidos', 'department' => ProcessDepartment::Impresion, 'sort_order' => 20, 'estimated_minutes' => 30, 'color' => 'info'],
            ['slug' => 'impresion-impresion-adhesivo', 'name' => 'Impresión adhesivo', 'department' => ProcessDepartment::Impresion, 'sort_order' => 30, 'estimated_minutes' => 25, 'color' => 'info'],
            ['slug' => 'impresion-preprensa', 'name' => 'Preprensa', 'department' => ProcessDepartment::Impresion, 'sort_order' => 40, 'estimated_minutes' => 15, 'color' => 'info'],
            ['slug' => 'laser-diseno', 'name' => 'Diseño', 'department' => ProcessDepartment::Laser, 'sort_order' => 10, 'estimated_minutes' => 30, 'color' => 'warning'],
            ['slug' => 'laser-corte', 'name' => 'Corte', 'department' => ProcessDepartment::Laser, 'sort_order' => 20, 'estimated_minutes' => 20, 'color' => 'warning'],
            ['slug' => 'laser-grabado', 'name' => 'Grabado', 'department' => ProcessDepartment::Laser, 'sort_order' => 30, 'estimated_minutes' => 25, 'color' => 'warning'],
            ['slug' => 'miscelanea-diseno', 'name' => 'Diseño', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 10, 'estimated_minutes' => 30, 'color' => 'primary'],
            ['slug' => 'miscelanea-solicitud-de-material', 'name' => 'Solicitud de material', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 20, 'estimated_minutes' => 10, 'color' => 'primary'],
            ['slug' => 'miscelanea-termoformado', 'name' => 'Termoformado', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 30, 'estimated_minutes' => 40, 'color' => 'primary'],
            ['slug' => 'miscelanea-corte', 'name' => 'Corte', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 40, 'estimated_minutes' => 20, 'color' => 'primary'],
            ['slug' => 'miscelanea-acabados', 'name' => 'Acabados', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 50, 'estimated_minutes' => 25, 'color' => 'primary'],
            ['slug' => 'miscelanea-grabado', 'name' => 'Grabado', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 60, 'estimated_minutes' => 25, 'color' => 'primary'],
            ['slug' => 'miscelanea-cantoneado-y-armado', 'name' => 'Cantoneado y armado', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 70, 'estimated_minutes' => 35, 'color' => 'primary'],
            ['slug' => 'miscelanea-iluminacion', 'name' => 'Iluminación', 'department' => ProcessDepartment::Miscelanea, 'sort_order' => 80, 'estimated_minutes' => 30, 'color' => 'primary'],
            ['slug' => 'termoformado-diseno', 'name' => 'Diseño', 'department' => ProcessDepartment::Termoformado, 'sort_order' => 10, 'estimated_minutes' => 30, 'color' => 'gray'],
            ['slug' => 'termoformado-corte-de-material', 'name' => 'Corte de material', 'department' => ProcessDepartment::Termoformado, 'sort_order' => 20, 'estimated_minutes' => 20, 'color' => 'gray'],
            ['slug' => 'termoformado-horneado', 'name' => 'Horneado', 'department' => ProcessDepartment::Termoformado, 'sort_order' => 30, 'estimated_minutes' => 45, 'color' => 'gray'],
            ['slug' => 'termoformado-acabados', 'name' => 'Acabados', 'department' => ProcessDepartment::Termoformado, 'sort_order' => 40, 'estimated_minutes' => 25, 'color' => 'gray'],
            ['slug' => 'avisos-corte', 'name' => 'Corte', 'department' => ProcessDepartment::Avisos, 'sort_order' => 10, 'estimated_minutes' => 20, 'color' => 'danger'],
            ['slug' => 'avisos-grabado', 'name' => 'Grabado', 'department' => ProcessDepartment::Avisos, 'sort_order' => 20, 'estimated_minutes' => 25, 'color' => 'danger'],
            ['slug' => 'avisos-cantoneado-y-armado', 'name' => 'Cantoneado y armado', 'department' => ProcessDepartment::Avisos, 'sort_order' => 30, 'estimated_minutes' => 35, 'color' => 'danger'],
            ['slug' => 'avisos-iluminacion', 'name' => 'Iluminación', 'department' => ProcessDepartment::Avisos, 'sort_order' => 40, 'estimated_minutes' => 30, 'color' => 'danger'],
            ['slug' => 'control-calidad-revision', 'name' => 'Revisión', 'department' => ProcessDepartment::ControlCalidad, 'sort_order' => 10, 'estimated_minutes' => 10, 'color' => 'success'],
            ['slug' => 'control-calidad-devolucion', 'name' => 'Devolución', 'department' => ProcessDepartment::ControlCalidad, 'sort_order' => 20, 'estimated_minutes' => 10, 'color' => 'success'],
            ['slug' => 'control-calidad-aprobacion', 'name' => 'Aprobación', 'department' => ProcessDepartment::ControlCalidad, 'sort_order' => 30, 'estimated_minutes' => 10, 'color' => 'success'],
        ];

        foreach ($processes as $process) {
            Process::updateOrCreate(
                ['slug' => $process['slug']],
                [...$process, 'is_active' => true],
            );
        }

        Process::query()
            ->whereNull('department')
            ->whereIn('name', ['Corte', 'Grabado', 'Pulido', 'Ensamble', 'Control de Calidad'])
            ->update(['is_active' => false]);
    }
}
