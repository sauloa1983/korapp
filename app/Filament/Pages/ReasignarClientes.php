<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ReasignarClientes extends Page
{
    public const UNASSIGNED = '__unassigned__';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión';

    protected static ?string $navigationLabel = 'Reasignar cartera';

    protected static ?string $title = 'Reasignar cartera';

    protected static ?string $slug = 'reasignar-clientes';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:ReasignarClientes');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ThreeExtraLarge;
    }

    public function mount(): void
    {
        $this->form->fill([
            'from_user_id' => null,
            'to_user_id' => null,
            'only_active' => true,
            'also_leads' => true,
        ]);
    }

    public function getHeading(): string | Htmlable
    {
        return 'Reasignar cartera';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Pasa todos los clientes (y opcionalmente prospectos) de un vendedor a otro, sin límite de cantidad.';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transferencia masiva')
                    ->description('Elige de quién salen y a quién llegan. Se actualizan todos los registros que coincidan, aunque sean cientos o miles.')
                    ->columns(2)
                    ->schema([
                        Select::make('from_user_id')
                            ->label('Desde')
                            ->options(fn (): array => $this->sourceOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText('Incluye «Sin asignar» para repartir la cartera libre.'),
                        Select::make('to_user_id')
                            ->label('Hacia')
                            ->options(fn (): array => $this->destinationOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText('Vendedor que recibirá la cartera.'),
                        Toggle::make('only_active')
                            ->label('Solo clientes activos')
                            ->default(true)
                            ->live()
                            ->inline(false),
                        Toggle::make('also_leads')
                            ->label('También reasignar prospectos')
                            ->helperText('Mueve los prospectos del mismo vendedor de origen.')
                            ->default(true)
                            ->live()
                            ->inline(false),
                        Placeholder::make('preview')
                            ->label('Resumen')
                            ->content(fn (Get $get): HtmlString => $this->previewHtml($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('transfer')
                    ->footer([
                        Actions::make([
                            Action::make('transfer')
                                ->label('Reasignar ahora')
                                ->color('primary')
                                ->requiresConfirmation()
                                ->modalHeading('Confirmar reasignación')
                                ->modalDescription(fn (): string => $this->confirmationMessage())
                                ->modalSubmitActionLabel('Sí, reasignar')
                                ->submit('form'),
                        ]),
                    ]),
            ]);
    }

    public function transfer(): void
    {
        $data = $this->form->getState();
        $from = $data['from_user_id'] ?? null;
        $to = (int) ($data['to_user_id'] ?? 0);
        $onlyActive = (bool) ($data['only_active'] ?? true);
        $alsoLeads = (bool) ($data['also_leads'] ?? false);

        if (blank($from) || $to < 1) {
            Notification::make()
                ->title('Selecciona origen y destino')
                ->danger()
                ->send();

            return;
        }

        if ((string) $from === (string) $to) {
            Notification::make()
                ->title('Origen y destino deben ser distintos')
                ->danger()
                ->send();

            return;
        }

        if (! User::query()->whereKey($to)->exists()) {
            Notification::make()
                ->title('El vendedor destino no existe')
                ->danger()
                ->send();

            return;
        }

        $customersMoved = 0;
        $leadsMoved = 0;

        DB::transaction(function () use ($from, $to, $onlyActive, $alsoLeads, &$customersMoved, &$leadsMoved): void {
            // Primero se toman los IDs: si se actualiza user_id dentro de chunkById se saltan filas.
            $customerIds = $this->customerQuery($from, $onlyActive)->pluck('id');
            $customersMoved = $customerIds->count();

            foreach ($customerIds->chunk(250) as $chunk) {
                Customer::query()
                    ->whereIn('id', $chunk)
                    ->update(['user_id' => $to]);
            }

            if ($alsoLeads) {
                $leadIds = $this->leadQuery($from)->pluck('id');
                $leadsMoved = $leadIds->count();

                foreach ($leadIds->chunk(250) as $chunk) {
                    Lead::query()
                        ->whereIn('id', $chunk)
                        ->update(['user_id' => $to]);
                }
            }
        });

        $body = "{$customersMoved} cliente(s) reasignado(s)";

        if ($alsoLeads) {
            $body .= " · {$leadsMoved} prospecto(s)";
        }

        Notification::make()
            ->title('Cartera reasignada')
            ->body($body)
            ->success()
            ->send();

        $this->form->fill([
            'from_user_id' => $from,
            'to_user_id' => $to,
            'only_active' => $onlyActive,
            'also_leads' => $alsoLeads,
        ]);
    }

    /** @return array<string, string> */
    protected function sourceOptions(): array
    {
        $options = [
            self::UNASSIGNED => 'Sin asignar',
        ];

        $ids = Customer::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->merge(
                Lead::query()->whereNotNull('user_id')->distinct()->pluck('user_id')
            )
            ->merge(
                User::query()->role(['Vendedor', 'super_admin'])->pluck('id')
            )
            ->unique()
            ->filter()
            ->values();

        $users = User::query()
            ->visibleInDirectory()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($users as $user) {
            $count = Customer::query()->where('user_id', $user->id)->count();
            $options[(string) $user->id] = "{$user->name} ({$count})";
        }

        return $options;
    }

    /** @return array<int, string> */
    protected function destinationOptions(): array
    {
        return User::query()
            ->visibleInDirectory()
            ->role(['Vendedor', 'super_admin'])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function customerQuery(string $from, bool $onlyActive): Builder
    {
        return Customer::query()
            ->when(
                $from === self::UNASSIGNED,
                fn (Builder $q): Builder => $q->whereNull('user_id'),
                fn (Builder $q): Builder => $q->where('user_id', $from),
            )
            ->when($onlyActive, fn (Builder $q): Builder => $q->where('is_active', true));
    }

    protected function leadQuery(string $from): Builder
    {
        return Lead::query()
            ->when(
                $from === self::UNASSIGNED,
                fn (Builder $q): Builder => $q->whereNull('user_id'),
                fn (Builder $q): Builder => $q->where('user_id', $from),
            );
    }

    protected function previewHtml(Get $get): HtmlString
    {
        $from = $get('from_user_id');
        $to = $get('to_user_id');
        $onlyActive = (bool) $get('only_active');
        $alsoLeads = (bool) $get('also_leads');

        if (blank($from) || blank($to)) {
            return new HtmlString('<span class="text-gray-500">Selecciona origen y destino para ver cuántos registros se moverán.</span>');
        }

        if ((string) $from === (string) $to) {
            return new HtmlString('<span class="text-danger-600 font-medium">El origen y el destino no pueden ser el mismo vendedor.</span>');
        }

        $customers = $this->customerQuery((string) $from, $onlyActive)->count();
        $leads = $alsoLeads ? $this->leadQuery((string) $from)->count() : 0;

        $fromLabel = $from === self::UNASSIGNED
            ? 'Sin asignar'
            : (User::query()->whereKey($from)->value('name') ?? 'Vendedor');
        $toLabel = User::query()->whereKey($to)->value('name') ?? 'Vendedor';

        $html = "<div class=\"space-y-1 text-sm\">"
            ."<p><strong>{$customers}</strong> cliente(s) pasarán de <strong>{$fromLabel}</strong> a <strong>{$toLabel}</strong>.</p>";

        if ($alsoLeads) {
            $html .= "<p><strong>{$leads}</strong> prospecto(s) también se reasignarán.</p>";
        }

        if ($customers === 0 && $leads === 0) {
            $html .= '<p class="text-warning-600">No hay registros para mover con estos filtros.</p>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected function confirmationMessage(): string
    {
        $data = $this->data ?? [];
        $from = $data['from_user_id'] ?? null;
        $to = $data['to_user_id'] ?? null;
        $onlyActive = (bool) ($data['only_active'] ?? true);
        $alsoLeads = (bool) ($data['also_leads'] ?? false);

        if (blank($from) || blank($to)) {
            return 'Completa origen y destino antes de continuar.';
        }

        $customers = $this->customerQuery((string) $from, $onlyActive)->count();
        $leads = $alsoLeads ? $this->leadQuery((string) $from)->count() : 0;

        $message = "Se reasignarán {$customers} cliente(s)";

        if ($alsoLeads) {
            $message .= " y {$leads} prospecto(s)";
        }

        return $message.'. Esta acción no se puede deshacer automáticamente.';
    }
}
