<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\Visit;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    protected static ?string $title = 'Agenda / historial de contactos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('subject')
                    ->label('Asunto')
                    ->required()
                    ->maxLength(255)
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
                DateTimePicker::make('next_follow_up_at')
                    ->label('Próximo seguimiento')
                    ->seconds(false)
                    ->native(false)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('outcome')
                    ->label('Resultado')
                    ->rows(2)
                    ->visible(fn (Get $get): bool => in_array($get('status'), [
                        VisitStatus::Realizada->value,
                        VisitStatus::NoAsistio->value,
                        VisitStatus::Realizada,
                        VisitStatus::NoAsistio,
                    ], true))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('next_follow_up_at')
                    ->label('Próx. seguimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agendar')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        $data['customer_id'] = $owner->customer_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Realizada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Visit $record): bool => $record->status === VisitStatus::Programada)
                    ->action(function (Visit $record): void {
                        $record->markCompleted();
                        Notification::make()->title('Contacto marcado como realizado')->success()->send();
                    }),
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->emptyStateHeading('Sin contactos')
            ->emptyStateDescription('Agenda la primera visita o llamada para este prospecto.')
            ->emptyStateIcon('heroicon-o-map-pin');
    }
}
