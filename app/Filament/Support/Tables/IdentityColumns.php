<?php

namespace App\Filament\Support\Tables;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class IdentityColumns
{
    /**
     * Avatar + etiqueta principal + línea secundaria atenuada (email por defecto).
     *
     * @return array{0: ImageColumn, 1: TextColumn}
     */
    public static function make(
        string $nameAttribute = 'name',
        string $secondaryAttribute = 'email',
        string $label = 'Cliente',
        string $avatarBackground = 'EEF2FF',
        string $avatarColor = '4F46E5',
    ): array {
        return [
            ImageColumn::make('_identity_avatar')
                ->label('')
                ->getStateUsing(fn (): null => null)
                ->defaultImageUrl(function (Model $record) use ($nameAttribute, $avatarBackground, $avatarColor): string {
                    $name = (string) data_get($record, $nameAttribute, '?');

                    return 'https://ui-avatars.com/api/?' . http_build_query([
                        'name' => $name,
                        'background' => $avatarBackground,
                        'color' => $avatarColor,
                        'bold' => 'true',
                        'format' => 'svg',
                    ]);
                })
                ->circular()
                ->imageSize(44)
                ->grow(false)
                ->toggleable(false),
            TextColumn::make($nameAttribute)
                ->label($label)
                ->description(fn (Model $record): ?string => filled(data_get($record, $secondaryAttribute))
                    ? (string) data_get($record, $secondaryAttribute)
                    : null)
                ->searchable([$nameAttribute, $secondaryAttribute])
                ->sortable()
                ->weight(FontWeight::SemiBold)
                ->wrap(),
        ];
    }
}
