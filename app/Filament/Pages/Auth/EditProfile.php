<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

class EditProfile extends BaseEditProfile
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Mi perfil';

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ThreeExtraLarge;
    }

    public function getHeading(): string | Htmlable
    {
        return 'Mi perfil';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Actualiza tu foto, datos personales y contraseña.';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data')
            ->columns(1);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Foto de perfil')
                    ->description('Se muestra en el menú lateral y en el panel.')
                    ->icon('heroicon-o-camera')
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->label('Imagen')
                            ->avatar()
                            ->imageEditor()
                            ->circleCropper()
                            ->directory('avatars')
                            ->disk('public')
                            ->visibility('public')
                            ->image()
                            ->maxSize(2048)
                            ->helperText('JPG o PNG, máximo 2 MB.'),
                    ]),
                Section::make('Información personal')
                    ->description('Estos datos se muestran en el panel y en notificaciones.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->autofocus(),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Seguridad')
                    ->description('Deja la contraseña en blanco si no deseas cambiarla.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state))
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->visible(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                        TextInput::make('pin')
                            ->label('PIN de operario')
                            ->helperText('Usado en estaciones de escaneo. Déjalo vacío para no cambiarlo.')
                            ->password()
                            ->revealable()
                            ->maxLength(10)
                            ->dehydrated(fn (#[SensitiveParameter] $state): bool => filled($state)),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['pin'], $data['password']);

        return $data;
    }

    public static function getLabel(): string
    {
        return 'Mi perfil';
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getUrl();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $passwordChanged = array_key_exists('password', $data) && filled($data['password']);

        $record = parent::handleRecordUpdate($record, $data);

        if ($passwordChanged) {
            $record->forceFill(['must_change_password' => false])->save();

            // Invalida cookies "Recordarme" en otros dispositivos.
            if (method_exists($record, 'setRememberToken')) {
                $record->setRememberToken(Str::random(60));
                $record->save();
            }
        }

        return $record;
    }
}
