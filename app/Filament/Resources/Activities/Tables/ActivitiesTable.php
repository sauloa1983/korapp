<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Support\ModelLabels;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Tipo de registro')
                    ->formatStateUsing(fn (?string $state): string => ModelLabels::forType($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject_id')
                    ->label('Identificador'),
                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextColumn::make('properties')
                    ->label('Cambios')
                    ->formatStateUsing(function ($state): string {
                        $data = $state instanceof \Illuminate\Support\Collection ? $state->toArray() : (array) $state;
                        $attributes = $data['attributes'] ?? [];

                        return collect($attributes)
                            ->map(fn ($v, $k): string => ModelLabels::forAttribute((string) $k).': '.(is_scalar($v) ? $v : json_encode($v)))
                            ->implode(', ') ?: '—';
                    })
                    ->wrap()
                    ->limit(80),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('Evento')
                    ->options([
                        'created' => 'Creado',
                        'updated' => 'Actualizado',
                        'deleted' => 'Eliminado',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Tipo de registro')
                    ->options(fn (): array => Activity::query()
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->filter()
                        ->mapWithKeys(fn (string $type): array => [$type => ModelLabels::forType($type)])
                        ->all()),
            ]);
    }
}
