<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerIdentityType;
use App\Models\Customer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    /**
     * Campos reutilizables para alta rápida (cotización, venta, etc.).
     * Las mayúsculas las aplica el modelo Customer al guardar.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function identityFields(bool $requireDocument = true): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre / Razón social')
                ->required()
                ->maxLength(255)
                ->autofocus()
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            TextInput::make('company_name')
                ->label('Nombre comercial')
                ->maxLength(255)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            TextInput::make('contact_name')
                ->label('Contacto')
                ->maxLength(255)
                ->placeholder('Nombre del contacto en la empresa')
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            Select::make('document_type')
                ->label('Tipo de documento')
                ->options(CustomerIdentityType::class)
                ->native(false)
                ->required($requireDocument)
                ->default(CustomerIdentityType::Nit->value),
            TextInput::make('tax_id')
                ->label('Número de documento')
                ->required($requireDocument)
                ->maxLength(255)
                ->placeholder('NIT / CC / CE')
                ->helperText('Obligatorio para registrar pedidos.')
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            TextInput::make('email')
                ->label('Correo')
                ->email()
                ->maxLength(255)
                ->placeholder('correo@empresa.com'),
            TextInput::make('phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(255)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            TextInput::make('address')
                ->label('Dirección')
                ->maxLength(255)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            TextInput::make('city')
                ->label('Ciudad')
                ->maxLength(255)
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
            Textarea::make('notes')
                ->label('Notas')
                ->rows(2)
                ->columnSpanFull()
                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
        ];
    }

    public static function createFromQuickForm(array $data, ?int $userId = null): Customer
    {
        return Customer::query()->create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'document_type' => $data['document_type'] ?? CustomerIdentityType::Nit,
            'tax_id' => $data['tax_id'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del cliente')
                    ->description('Los textos se guardan en MAYÚSCULAS (excepto el correo). El documento es obligatorio para pedidos.')
                    ->columns(2)
                    ->schema([
                        ...static::identityFields(requireDocument: true),
                        Select::make('user_id')
                            ->label('Vendedor asignado')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->visibleInDirectory()
                                    ->where(function (Builder $q): void {
                                        $q->role(['Vendedor', 'super_admin'])
                                            ->orWhere('id', auth()->id());
                                    })
                                    ->orderSellersFirst()
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->default(fn (): ?int => auth()->id())
                            ->placeholder('Sin asignar')
                            ->helperText('Si se deja vacío o el vendedor se va, el cliente queda en «Sin asignar».'),
                        Toggle::make('is_active')
                            ->label('Cliente activo')
                            ->helperText('Los clientes inactivos no aparecen en el punto de venta.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpanFull(),
                        Toggle::make('is_retenedor')
                            ->label('Retenedor')
                            ->helperText('Si el cliente es agente retenedor, la cotización aplica retención en la fuente.')
                            ->default(false)
                            ->live()
                            ->inline(false),
                        TextInput::make('retenedor_percent')
                            ->label('Retenedor %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0)
                            ->visible(fn ($get): bool => (bool) $get('is_retenedor'))
                            ->required(fn ($get): bool => (bool) $get('is_retenedor'))
                            ->helperText('Porcentaje de retención que se aplicará sobre el subtotal de la cotización.'),
                    ]),
            ]);
    }
}
