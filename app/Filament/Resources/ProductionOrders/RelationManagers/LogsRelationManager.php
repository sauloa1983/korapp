<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Enums\ProductionLogStatus;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Trazabilidad y tiempos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('notes')
                ->label('Observaciones del área')
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->defaultGroup('process.department')
            ->groups([
                Group::make('process.department')
                    ->label('Departamento')
                    ->getTitleFromRecordUsing(fn ($record): string => $record->process?->department?->getLabel()
                        ?? 'Sin departamento'),
            ])
            ->columns([
                TextColumn::make('sequence')
                    ->label('#')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('process.name')
                    ->label('Etapa'),
                TextColumn::make('process.department')
                    ->label('Depto')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Responsable')
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->label('Fi')
                    ->dateTime('d/m H:i')
                    ->placeholder('—'),
                TextColumn::make('ended_at')
                    ->label('Fs')
                    ->dateTime('d/m H:i')
                    ->placeholder('—'),
                TextColumn::make('duration_for_humans')
                    ->label('Duración')
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                TextColumn::make('notes')
                    ->label('Observaciones')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('qr_token')
                    ->label('Token QR')
                    ->copyable()
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('generatePipeline')
                    ->label('Generar flujo')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->getOwnerRecord()->logs()->count() === 0)
                    ->action(function (): void {
                        $this->getOwnerRecord()->generatePipeline();
                        Notification::make()->title('Flujo generado')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('scan')
                    ->label(fn ($record): string => $record->status === ProductionLogStatus::EnEspera ? 'Iniciar' : 'Finalizar')
                    ->icon('heroicon-o-qr-code')
                    ->color(fn ($record): string => $record->status === ProductionLogStatus::EnEspera ? 'success' : 'warning')
                    ->disabled(fn ($record): bool => $record->status === ProductionLogStatus::Terminado)
                    ->requiresConfirmation()
                    ->modalHeading('Simular escaneo de QR')
                    ->modalDescription(fn ($record): string => 'Etapa: '.$record->process->name.'. El escaneo registra el timestamp y calcula el tiempo.')
                    ->action(function ($record): void {
                        $result = $record->handleScan(auth()->id());

                        $messages = [
                            'iniciada' => 'Etapa iniciada. Cronómetro en marcha.',
                            'finalizada' => 'Etapa finalizada. Tiempo calculado: '.$record->fresh()->duration_for_humans.'.',
                            'sin_cambios' => 'La etapa ya estaba terminada.',
                        ];

                        Notification::make()
                            ->title($messages[$result])
                            ->success()
                            ->send();
                    }),
                Action::make('notes')
                    ->label('Obs.')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('gray')
                    ->fillForm(fn ($record): array => ['notes' => $record->notes])
                    ->form([
                        Textarea::make('notes')
                            ->label('Observaciones del área')
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update(['notes' => $data['notes'] ?? null]);
                        Notification::make()->title('Observaciones guardadas')->success()->send();
                    }),
                Action::make('qr')
                    ->label('Ver QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalHeading(fn ($record): string => 'QR · '.$record->process->name)
                    ->modalContent(fn ($record): HtmlString => static::renderQr($record->qr_token)),
            ]);
    }

    protected static function renderQr(string $token): HtmlString
    {
        $url = route('scan.show', $token);

        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => false,
            'scale' => 6,
        ]);

        $svg = (new QRCode($options))->render($url);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;padding:1rem;">'
            .'<div style="width:220px;">'.$svg.'</div>'
            .'<code style="font-size:.7rem;word-break:break-all;text-align:center;">'.e($url).'</code>'
            .'</div>'
        );
    }
}
