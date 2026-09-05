<?php

namespace App\Support;

use App\Filament\Pages\ConfiguracionEmpresa;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EscaneoOperario;
use App\Filament\Pages\ReasignarClientes;
use App\Filament\Pages\Reportes;
use App\Filament\Pages\SalesDashboard;
use App\Filament\Pages\SalesPipeline;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Processes\ProcessResource;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Visits\VisitResource;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;

class TutorialGuide
{
    /**
     * @return array<string, array{title: string, goal: string, minutes: string}>
     */
    public static function lessons(): array
    {
        return [
            'bienvenida' => [
                'title' => 'Bienvenida',
                'goal' => 'Cómo usar este tutorial',
                'minutes' => '2 min',
            ],
            'acceso' => [
                'title' => 'Entrar al sistema',
                'goal' => 'Inicio de sesión, perfil y roles',
                'minutes' => '3 min',
            ],
            'arranque' => [
                'title' => 'Primera configuración',
                'goal' => 'Orden recomendado al empezar',
                'minutes' => '5 min',
            ],
            'empresa' => [
                'title' => 'Tu empresa',
                'goal' => 'Logo, datos y apariencia',
                'minutes' => '4 min',
            ],
            'seguridad' => [
                'title' => 'Usuarios del equipo',
                'goal' => 'Cuentas y permisos',
                'minutes' => '5 min',
            ],
            'clientes' => [
                'title' => 'Clientes',
                'goal' => 'Alta, importación y cartera',
                'minutes' => '6 min',
            ],
            'ventas' => [
                'title' => 'Ventas y CRM',
                'goal' => 'Prospectos, cotizaciones y pedidos',
                'minutes' => '10 min',
            ],
            'pedido' => [
                'title' => 'Pedido completo',
                'goal' => 'Cotización → OP → Venta → Entrega',
                'minutes' => '8 min',
            ],
            'produccion' => [
                'title' => 'Producción',
                'goal' => 'Órdenes, QR y escaneo',
                'minutes' => '8 min',
            ],
            'reportes' => [
                'title' => 'Reportes',
                'goal' => 'Indicadores y auditoría',
                'minutes' => '3 min',
            ],
            'flujos' => [
                'title' => 'Día a día',
                'goal' => 'Flujos completos de trabajo',
                'minutes' => '5 min',
            ],
            'faq' => [
                'title' => 'Dudas frecuentes',
                'goal' => 'Respuestas rápidas',
                'minutes' => '3 min',
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function links(): array
    {
        return [
            'dashboard' => rescue(fn () => Dashboard::getUrl(), null, false),
            'empresa' => rescue(fn () => ConfiguracionEmpresa::getUrl(), null, false),
            'usuarios' => rescue(fn () => UserResource::getUrl('index'), null, false),
            'roles' => rescue(fn () => RoleResource::getUrl('index'), null, false),
            'clientes' => rescue(fn () => CustomerResource::getUrl('index'), null, false),
            'plantilla_clientes' => route('customers.import-template'),
            'reasignar' => rescue(fn () => ReasignarClientes::getUrl(), null, false),
            'panel_ventas' => rescue(fn () => SalesDashboard::getUrl(), null, false),
            'prospectos' => rescue(fn () => LeadResource::getUrl('index'), null, false),
            'embudo' => rescue(fn () => SalesPipeline::getUrl(), null, false),
            'agenda' => rescue(fn () => VisitResource::getUrl('index'), null, false),
            'cotizaciones' => rescue(fn () => QuoteResource::getUrl('index'), null, false),
            'ventas' => rescue(fn () => SaleResource::getUrl('index'), null, false),
            'procesos' => rescue(fn () => ProcessResource::getUrl('index'), null, false),
            'ordenes' => rescue(fn () => ProductionOrderResource::getUrl('index'), null, false),
            'escaneo' => rescue(fn () => EscaneoOperario::getUrl(), null, false),
            'reportes' => rescue(fn () => Reportes::getUrl(), null, false),
            'auditoria' => rescue(fn () => ActivityResource::getUrl('index'), null, false),
        ];
    }
}
