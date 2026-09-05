<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetTemporaryPassword')
                ->label('Restablecer contraseña inicial')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Restablecer contraseña inicial')
                ->modalDescription('Se asignará la contraseña inicial predeterminada y el usuario deberá cambiarla antes de entrar al sistema.')
                ->action(function (): void {
                    $password = (string) config('korapp.default_user_password');

                    $this->record->forceFill([
                        'password' => $password,
                        'must_change_password' => true,
                    ])->save();

                    Notification::make()
                        ->title('Contraseña restablecida')
                        ->body("Contraseña inicial: {$password}. Deberá cambiarla al entrar.")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->getKey() === auth()->id()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $defaultPassword = (string) config('korapp.default_user_password');
            $data['must_change_password'] = ($data['password'] === $defaultPassword);
        }

        return $data;
    }
}
