@php
    $route = request()->route()?->getName() ?? '';
    $title = 'Inicio';

    $map = [
        'customers' => 'Clientes',
        'reasignar-clientes' => 'Reasignar cartera',
        'sales' => 'Ventas',
        'punto-de-venta' => 'Punto de venta',
        'items' => 'Artículos',
        'item-categories' => 'Categorías',
        'warehouses' => 'Bodegas',
        'suppliers' => 'Proveedores',
        'stock-movements' => 'Movimientos',
        'production-orders' => 'Órdenes',
        'processes' => 'Procesos',
        'escaneo-operario' => 'Escaneo',
        'purchases' => 'Compras',
        'reportes' => 'Reportes',
        'activities' => 'Actividades',
        'shield' => 'Roles',
        'leads' => 'Prospectos',
        'visits' => 'Agenda comercial',
        'calendario' => 'Calendario comercial',
        'quotes' => 'Cotizaciones',
        'pipeline-ventas' => 'Embudo de ventas',
        'dashboard-ventas' => 'Panel de ventas',
        'configuracion-empresa' => 'Empresa',
        'dashboard' => 'Inicio',
    ];

    foreach ($map as $needle => $label) {
        if (str_contains($route, $needle)) {
            $title = $label;
            break;
        }
    }
@endphp

<div class="saas-topbar-title-wrap">
    <h1 class="saas-topbar-page-title">{{ $title }}</h1>
</div>
