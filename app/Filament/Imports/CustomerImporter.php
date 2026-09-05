<?php

namespace App\Filament\Imports;

use App\Models\Customer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class CustomerImporter extends Importer
{
    protected static ?string $model = Customer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nombre / Razón social')
                ->exampleHeader('nombre')
                ->examples(['ACME SAS', 'JUAN PÉREZ'])
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('company_name')
                ->label('Nombre comercial')
                ->exampleHeader('nombre_comercial')
                ->examples(['ACME', ''])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('contact_name')
                ->label('Contacto')
                ->exampleHeader('contacto')
                ->examples(['MARÍA LÓPEZ', 'PEDRO GÓMEZ'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('document_type')
                ->label('Tipo documento (nit|cc|ce|pasaporte|otro)')
                ->exampleHeader('tipo_documento')
                ->examples(['nit', 'cc'])
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('tax_id')
                ->label('Número de documento')
                ->exampleHeader('nit')
                ->examples(['900123456-1', '1020304050'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('email')
                ->label('Correo')
                ->exampleHeader('correo')
                ->examples(['compras@acme.com', 'juan@correo.com'])
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('phone')
                ->label('Teléfono')
                ->exampleHeader('telefono')
                ->examples(['3001234567', '6012345678'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('address')
                ->label('Dirección')
                ->exampleHeader('direccion')
                ->examples(['CALLE 10 #20-30', 'CRA 45 #12-15'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('city')
                ->label('Ciudad')
                ->exampleHeader('ciudad')
                ->examples(['BOGOTÁ', 'MEDELLÍN'])
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('notes')
                ->label('Notas')
                ->exampleHeader('notas')
                ->examples(['CLIENTE MAYORISTA', 'ENTREGA MARTES'])
                ->rules(['nullable']),
            ImportColumn::make('is_active')
                ->label('Activo (1=sí, 0=no)')
                ->exampleHeader('activo')
                ->examples(['1', '1'])
                ->boolean()
                ->rules(['nullable', 'boolean']),
        ];
    }

    public function resolveRecord(): Customer
    {
        $taxId = filled($this->data['tax_id'] ?? null) ? trim((string) $this->data['tax_id']) : null;
        $email = filled($this->data['email'] ?? null) ? trim((string) $this->data['email']) : null;

        if ($taxId) {
            $existing = Customer::withTrashed()->where('tax_id', $taxId)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing;
            }
        }

        if ($email) {
            $existing = Customer::withTrashed()->where('email', $email)->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                return $existing;
            }
        }

        return new Customer;
    }

    protected function beforeFill(): void
    {
        if (! array_key_exists('is_active', $this->data) || blank($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }
    }

    protected function beforeSave(): void
    {
        if (blank($this->record->user_id)) {
            $this->record->user_id = auth()->id() ?? $this->import->user_id;
        }
    }

    public function getJobConnection(): ?string
    {
        // Procesa al momento para no depender del worker de colas.
        return 'sync';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Importación de clientes terminada: '
            .Number::format($import->successful_rows).' '
            .str('fila')->plural($import->successful_rows).' procesada(s).';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '
                .str('fila')->plural($failedRowsCount).' fallaron.';
        }

        return $body;
    }
}
