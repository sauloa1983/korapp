<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = (string) config('korapp.default_user_password');
        $data['must_change_password'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $password = (string) config('korapp.default_user_password');

        Notification::make()
            ->title('Usuario creado')
            ->body("Contraseña inicial: {$password}. Deberá cambiarla antes de usar el sistema.")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
