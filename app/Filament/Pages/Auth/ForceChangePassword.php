<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Dashboard;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ForceChangePassword extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Cambiar contraseña';

    protected static ?string $title = 'Cambiar contraseña';

    protected static ?string $slug = 'cambiar-contrasena';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->check();
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Large;
    }

    public function getHeading(): string | Htmlable
    {
        return 'Cambia tu contraseña';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Por seguridad debes definir una contraseña nueva antes de continuar.';
    }

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user === null) {
            $this->redirect(filament()->getLoginUrl());

            return;
        }

        $user->refresh();

        if (! $user->mustChangePasswordBeforeAccess()) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nueva contraseña')
                    ->description('No puedes seguir usando la contraseña inicial predeterminada.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nueva contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->rule(fn (): \Illuminate\Validation\Rules\Password => Password::min(8))
                            ->autocomplete('new-password')
                            ->helperText('Mínimo 8 caracteres. No puede ser la contraseña inicial predeterminada.')
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete('new-password')
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Guardar y continuar')
                                ->submit('form')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        if ($user === null) {
            return;
        }

        $password = (string) ($data['password'] ?? '');
        $defaultPassword = (string) config('korapp.default_user_password');
        $currentHash = (string) ($user->getAuthPassword() ?: $user->getRawOriginal('password') ?: '');

        if (
            $password === ''
            || $password === $defaultPassword
            || ($currentHash !== '' && Hash::check($password, $currentHash))
        ) {
            Notification::make()
                ->title('Elige otra contraseña')
                ->body('No puedes usar la contraseña inicial predeterminada ni repetir la actual.')
                ->danger()
                ->send();

            return;
        }

        $user->forceFill([
            'password' => $password,
            'must_change_password' => false,
        ])->save();

        if (method_exists($user, 'setRememberToken')) {
            $user->setRememberToken(Str::random(60));
            $user->save();
        }

        Notification::make()
            ->title('Contraseña actualizada')
            ->body('Ya puedes usar el sistema con normalidad.')
            ->success()
            ->send();

        $this->redirect(Dashboard::getUrl(), navigate: true);
    }
}
