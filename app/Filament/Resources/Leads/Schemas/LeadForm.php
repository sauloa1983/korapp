<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadStage;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->extraAttributes(['class' => 'fi-lead-form-panels'])
            ->components([
                Section::make('Información de contacto')
                    ->description('Datos de la persona o empresa con la que estás negociando.')
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('primary')
                    ->columns(1)
                    ->extraAttributes(['class' => 'fi-lead-panel-card'])
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del contacto')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->placeholder('Ingresa el nombre completo')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
                        TextInput::make('company')
                            ->label('Empresa')
                            ->maxLength(255)
                            ->placeholder('Nombre de la empresa (opcional)')
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('correo@empresa.com'),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+57 300 000 0000'),
                    ]),
                Section::make('Información de la negociación')
                    ->description('Etapa del embudo, responsable y tamaño estimado del negocio.')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->iconColor('primary')
                    ->columns(1)
                    ->extraAttributes(['class' => 'fi-lead-panel-card'])
                    ->schema([
                        Select::make('stage')
                            ->label('Etapa')
                            ->options(LeadStage::class)
                            ->required()
                            ->native(false)
                            ->default(LeadStage::New)
                            ->helperText('Selecciona la etapa actual del proceso de ventas.'),
                        Select::make('user_id')
                            ->label('Vendedor asignado')
                            ->options(fn (): array => User::query()
                                ->visibleInDirectory()
                                ->where(function (Builder $q): void {
                                    $q->role(['Vendedor', 'super_admin'])
                                        ->orWhere('id', auth()->id());
                                })
                                ->with('roles')
                                ->orderBy('name')
                                ->get()
                                ->sortBy([
                                    fn (User $user): int => $user->hasRole('Vendedor') ? 0 : 1,
                                    fn (User $user): string => mb_strtoupper($user->name),
                                ])
                                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Selecciona un vendedor')
                            ->helperText('Si no asignas un vendedor, quedará sin asignar.'),
                        \App\Filament\Support\MoneyFormat::copInput('value', 'Valor estimado del negocio')
                            ->placeholder('500.000')
                            ->helperText('Se formatea solo. Ej: 500000 → 500.000')
                            ->default(null)
                            ->extraInputAttributes([
                                'class' => 'fi-input text-lg font-semibold tracking-tight',
                                'style' => 'font-variant-numeric: tabular-nums;',
                                'inputmode' => 'numeric',
                            ]),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->placeholder('Información adicional sobre este prospecto, objetivos, comentarios…'),
                        Select::make('customer_id')
                            ->label('Cliente vinculado')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Customer $record): string => $record->displayName()
                                .(filled($record->tax_id) ? " · {$record->tax_id}" : ''))
                            ->searchable(['name', 'company_name', 'tax_id', 'email'])
                            ->preload()
                            ->nullable()
                            ->placeholder('Buscar y seleccionar cliente (opcional)')
                            ->helperText('Opcional. Si ya existe el cliente, vincúlalo aquí; si no, se creará al marcar Ganado.'),
                    ]),
            ]);
    }
}
