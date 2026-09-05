<?php

namespace App\Filament\Resources\Processes\Tables;

use App\Enums\ProcessDepartment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Etapa')
                    ->searchable(),
                TextColumn::make('department')
                    ->label('Departamento')
                    ->badge()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('estimated_minutes')
                    ->label('Est.')
                    ->suffix(' min')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('color')
                    ->label('Color')
                    ->badge()
                    ->color(fn ($state) => $state ?: 'gray')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->label('Departamento')
                    ->options(ProcessDepartment::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
