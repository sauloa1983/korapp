<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaultPassword = (string) config('korapp.default_user_password');

        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->description('Credenciales de acceso al panel. Asigna un rol para que pueda entrar.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper(trim($state), 'UTF-8') : $state),
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Placeholder::make('default_password_info')
                            ->label('Contraseña inicial')
                            ->content(new HtmlString(
                                'Se asignará automáticamente: <strong>'.e($defaultPassword).'</strong><br>'
                                .'Antes de usar el sistema, el usuario deberá cambiarla obligatoriamente.'
                            ))
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->confirmed()
                            ->minLength(8)
                            ->helperText('Déjala vacía para no cambiarla. Si usas la contraseña inicial predeterminada, el usuario deberá cambiarla al entrar.')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        TextInput::make('password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && filled($get('password'))),
                        Select::make('roles')
                            ->label('Rol')
                            ->relationship('roles', 'name')
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record): string => \App\Support\ModelLabels::forRole($record->name))
                            ->helperText('Define qué puede hacer el usuario en el sistema. Solo un rol por usuario.')
                            ->columnSpanFull(),
                        TextInput::make('pin')
                            ->label('PIN de producción')
                            ->numeric()
                            ->maxLength(10)
                            ->nullable()
                            ->helperText('Opcional. Usado por operarios en el escaneo de producción.')
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
