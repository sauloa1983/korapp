<?php

namespace App\Filament\Resources\Visits\Schemas;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VisitForm
{
    public static function configure(Schema $schema, bool $hideContactFields = false): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ...($hideContactFields ? [] : [
                    Section::make('1. Contacto')
                        ->description('Elige prospecto, cliente, o ambos si ya se convirtió.')
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            Select::make('lead_id')
                                ->label('Prospecto')
                                ->relationship('lead', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $customerId = Lead::query()->whereKey($state)->value('customer_id');

                                    if (filled($customerId)) {
                                        $set('customer_id', $customerId);
                                    }
                                })
                                ->helperText('Negociación o cierre de venta.'),
                            Select::make('customer_id')
                                ->label('Cliente')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->preload()
                                ->helperText('Posventa, cobro o fidelización.'),
                        ]),
                ]),

                Section::make('2. Actividad')
                    ->description('Qué vas a hacer, cuándo y quién lo atiende.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('subject')
                            ->label('Asunto')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej. Visita a planta, entrega de cotización')
                            ->columnSpanFull(),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(VisitType::class)
                            ->default(VisitType::Visita)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('Estado')
                            ->options(VisitStatus::class)
                            ->default(VisitStatus::Programada)
                            ->required()
                            ->native(false)
                            ->live(),
                        DateTimePicker::make('scheduled_at')
                            ->label('Fecha y hora')
                            ->required()
                            ->seconds(false)
                            ->default(now()->setMinute(0)->addHour())
                            ->native(false),
                        Select::make('user_id')
                            ->label('Responsable')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->id())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notas / preparación')
                            ->rows(3)
                            ->placeholder('Qué llevar, qué preguntar, dirección, etc.')
                            ->columnSpanFull(),
                    ]),

                Section::make('3. Seguimiento')
                    ->description('Agenda el próximo contacto. El resultado solo aparece cuando ya se realizó.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DateTimePicker::make('next_follow_up_at')
                            ->label('Próximo seguimiento')
                            ->seconds(false)
                            ->native(false)
                            ->helperText('Opcional. Aparece en el panel de ventas.')
                            ->columnSpanFull(),
                        Textarea::make('outcome')
                            ->label('Resultado del contacto')
                            ->rows(3)
                            ->placeholder('Ej. Interesado, pidió cotización, no estaba…')
                            ->visible(fn (Get $get): bool => in_array($get('status'), [
                                VisitStatus::Realizada->value,
                                VisitStatus::NoAsistio->value,
                                VisitStatus::Realizada,
                                VisitStatus::NoAsistio,
                            ], true))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
