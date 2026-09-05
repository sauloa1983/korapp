<?php

namespace App\Filament\Pages;

use App\Models\CompanySetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ConfiguracionEmpresa extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?string $navigationLabel = 'Empresa';

    protected static ?string $title = 'Configuración de empresa';

    protected static ?string $slug = 'configuracion-empresa';

    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:ConfiguracionEmpresa');
    }

    public function mount(): void
    {
        $this->form->fill(CompanySetting::current()->attributesToArray());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidad de la empresa')
                    ->description('Logo y nombre del cliente que aparecen en el menú del aplicativo.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la empresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('tagline')
                            ->label('Eslogan / descripción corta')
                            ->maxLength(255)
                            ->placeholder('Ej. Gestión de ventas y producción')
                            ->columnSpan(1),
                        FileUpload::make('logo_path')
                            ->label('Logo del aplicativo')
                            ->image()
                            ->directory('company')
                            ->disk('public')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->imagePreviewHeight('160')
                            ->maxSize(4096)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                            ->helperText('Reemplaza el logo por defecto. PNG o SVG con fondo transparente recomendado.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Datos de contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label('NIT / Documento')
                            ->maxLength(255),
                        TextInput::make('website')
                            ->label('Sitio web')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://'),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Impuestos (IVA)')
                    ->description('Define si la empresa cobra IVA y el porcentaje aplicado a ventas, cotizaciones y compras.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('charges_iva')
                            ->label('¿Cobra IVA?')
                            ->helperText('Si está activo, se calculará el IVA sobre el subtotal de cada documento.')
                            ->live()
                            ->inline(false),
                        TextInput::make('iva_rate')
                            ->label('Porcentaje de IVA (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->default(19)
                            ->required(fn (callable $get): bool => (bool) $get('charges_iva'))
                            ->visible(fn (callable $get): bool => (bool) $get('charges_iva'))
                            ->helperText('Ejemplo Colombia: 19'),
                    ]),
                Section::make('Datos bancarios')
                    ->description('Se muestran en la propuesta comercial / PDF de cotización.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('bank_name')
                            ->label('Banco')
                            ->maxLength(255),
                        TextInput::make('bank_account_type')
                            ->label('Tipo de cuenta')
                            ->maxLength(255)
                            ->placeholder('Ahorros / Corriente'),
                        TextInput::make('bank_account_number')
                            ->label('Número de cuenta')
                            ->maxLength(255),
                        TextInput::make('bank_account_holder')
                            ->label('Titular')
                            ->maxLength(255),
                    ]),
                Section::make('Apariencia del panel')
                    ->description('Elige un tema predefinido o un color personalizado para el menú lateral.')
                    ->columns(2)
                    ->schema([
                        Select::make('sidebar_theme')
                            ->label('Tema del menú lateral')
                            ->options([
                                'light' => 'Claro (blanco)',
                                'indigo' => 'Índigo oscuro',
                                'custom' => 'Personalizado',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if ($state === 'light') {
                                    $set('sidebar_color', '#FFFFFF');
                                }

                                if ($state === 'indigo') {
                                    $set('sidebar_color', '#313A82');
                                }

                                if ($state === 'custom' && blank($get('sidebar_color'))) {
                                    $set('sidebar_color', '#313A82');
                                }
                            }),
                        ColorPicker::make('sidebar_color')
                            ->label('Color del menú')
                            ->required(fn (callable $get): bool => $get('sidebar_theme') === 'custom')
                            ->visible(fn (callable $get): bool => $get('sidebar_theme') === 'custom')
                            ->helperText('El texto e iconos se adaptan automáticamente según el color.'),
                        ColorPicker::make('primary_color')
                            ->label('Color primario')
                            ->required()
                            ->helperText('Botones, enlaces y acentos del panel.'),
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
                                ->label('Guardar cambios')
                                ->submit('form')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! ($data['charges_iva'] ?? false)) {
            $data['iva_rate'] = 0;
        }

        $settings = CompanySetting::current();
        $settings->fill($data);
        $settings->save();

        Notification::make()
            ->title('Configuración guardada')
            ->body('El logo, impuestos y datos de la empresa se actualizaron correctamente.')
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: true);
    }

    public function getHeading(): string | Htmlable
    {
        return 'Configuración de empresa';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Actualiza el logo, impuestos, datos de contacto y apariencia del panel.';
    }
}
