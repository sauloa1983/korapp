<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\Tables\IdentityColumns;
use App\Support\ModelLabels;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ...IdentityColumns::make(
                    nameAttribute: 'name',
                    secondaryAttribute: 'email',
                    label: 'Usuario',
                ),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): string => ModelLabels::forRole($state))
                    ->placeholder('Sin rol'),
                TextColumn::make('must_change_password')
                    ->label('Acceso')
                    ->badge()
                    ->getStateUsing(fn ($record): bool => $record->mustChangePasswordBeforeAccess())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Debe cambiar contraseña' : 'Activo')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => ModelLabels::forRole($record->name))
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn ($record): bool => $record->id === auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
