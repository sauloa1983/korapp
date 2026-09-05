<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected ?string $subheading = 'Crea y administra cuentas de acceso al panel.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear usuario')
                ->icon('heroicon-m-plus'),
        ];
    }
}
