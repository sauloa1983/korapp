<?php

namespace App\Support;

class ModelLabels
{
    /**
     * Traduce el nombre de clase / morph type a etiqueta en español.
     */
    public static function forType(?string $type): string
    {
        if (blank($type)) {
            return '—';
        }

        return match (class_basename($type)) {
            'Sale' => 'Venta',
            'SaleItem' => 'Línea de venta',
            'SaleReturn' => 'Devolución de venta',
            'SaleReturnItem' => 'Línea de devolución de venta',
            'Purchase' => 'Compra',
            'PurchaseItem' => 'Línea de compra',
            'PurchaseReturn' => 'Devolución de compra',
            'PurchaseReturnItem' => 'Línea de devolución de compra',
            'Item' => 'Artículo',
            'Customer' => 'Cliente',
            'CustomerDocument' => 'Documento de cliente',
            'Supplier' => 'Proveedor',
            'Warehouse' => 'Bodega',
            'Lead' => 'Prospecto',
            'Quote' => 'Cotización',
            'QuoteItem' => 'Línea de cotización',
            'Visit' => 'Visita',
            'ProductionOrder' => 'Orden de producción',
            'ProductionLog' => 'Registro de producción',
            'Process' => 'Proceso',
            'User' => 'Usuario',
            'StockMovement' => 'Movimiento',
            'ItemCategory' => 'Categoría',
            'CompanySetting' => 'Empresa',
            default => class_basename($type),
        };
    }

    /**
     * Traduce nombres de atributos técnicos de auditoría a español.
     */
    public static function forAttribute(string $attribute): string
    {
        return match ($attribute) {
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'status' => 'estado',
            'code' => 'código',
            'subtotal' => 'subtotal',
            'iva_rate' => 'tarifa IVA',
            'iva_amount' => 'valor IVA',
            'charges_iva' => 'cobra IVA',
            'total' => 'total',
            'notes' => 'notas',
            'quantity' => 'cantidad',
            'stock' => 'existencia',
            'min_stock' => 'existencia mínima',
            'cost' => 'costo',
            'price' => 'precio',
            'sku' => 'SKU',
            'type' => 'tipo',
            'stage' => 'etapa',
            'value' => 'valor',
            'company' => 'empresa',
            'tax_id' => 'NIT / documento',
            'document_type' => 'tipo de documento',
            'company_name' => 'nombre comercial',
            'city' => 'ciudad',
            'customer_id' => 'cliente',
            'supplier_id' => 'proveedor',
            'warehouse_id' => 'bodega',
            'user_id' => 'usuario',
            'item_id' => 'artículo',
            'lead_id' => 'prospecto',
            'quote_id' => 'cotización',
            'sale_id' => 'venta',
            'purchase_id' => 'compra',
            'item_category_id' => 'categoría',
            'invoice_number' => 'factura',
            'invoice_sequence' => 'consecutivo',
            'einvoice_status' => 'estado DIAN',
            'einvoice_uuid' => 'CUFE',
            'sold_at' => 'fecha de venta',
            'valid_until' => 'válida hasta',
            'is_active' => 'activo',
            'is_default' => 'predeterminado',
            'unit_of_measure' => 'unidad de medida',
            'unit_price' => 'precio unitario',
            'unit_cost' => 'costo unitario',
            'line_total' => 'subtotal',
            'primary_color' => 'color principal',
            'must_change_password' => 'debe cambiar contraseña',
            'pin' => 'PIN',
            'address' => 'dirección',
            'description' => 'descripción',
            'sort_order' => 'orden',
            'estimated_minutes' => 'minutos estimados',
            'color' => 'color',
            'due_at' => 'entrega',
            'requested_at' => 'solicitud',
            'started_at' => 'inicio',
            'ended_at' => 'fin',
            'converted_at' => 'convertido',
            'password' => 'contraseña',
            default => str_replace('_', ' ', $attribute),
        };
    }

    /**
     * Etiqueta visible para roles internos.
     */
    public static function forRole(?string $role): string
    {
        if (blank($role)) {
            return '—';
        }

        return match ($role) {
            'super_admin' => 'Administrador',
            'Operario' => 'Operario',
            'Vendedor' => 'Vendedor',
            default => $role,
        };
    }
}
