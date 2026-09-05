<?php

namespace App\Filament\Pages;

use App\Enums\SernaItemType;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Support\MoneyFormat;
use App\Filament\Support\SernaItemFormFields;
use App\Http\Requests\Serna\CalculateSernaQuoteRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\Serna\Ai\SernaAiAssistant;
use App\Services\Serna\Ai\SernaQuoteReadinessChecker;
use App\Services\Serna\CreateQuoteFromSernaCalculation;
use App\Services\Serna\SernaQuotationEngine;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class CotizadorAcrilico extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Cotizador';

    protected static ?string $title = 'Cotizador comercial Serna 2026';

    protected static ?string $slug = 'cotizador-acrilico';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:CotizadorAcrilico')
            || $user->can('Create:Quote');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::SevenExtraLarge;
    }

    public function mount(): void
    {
        $this->form->fill([
            'customer_id' => null,
            'lead_id' => null,
            'project_name' => null,
            'contact_name' => null,
            'validity_days' => 10,
            'valid_until' => now()->addDays(10)->toDateString(),
            'delivery_date' => null,
            'delivery_note' => 'A CONVENIR',
            'payment_form' => 'CONTADO',
            'advance_percent' => 50,
            'withholding_rate' => 0,
            'notes' => 'TRANSPORTE E INSTALACION INCLUIDOS. NO INCLUYE ACOMETIDA ELECTRICA.',
            'terms' => implode("\n", [
                'FORMA DE PAGO: 50% DE ANTICIPO Y SALDO CONTRA ENTREGA.',
                'Vigencia de la cotización: 10 días calendario.',
            ]),
            'ai_prompt' => null,
            'ai_explanation' => null,
            'quote_blockers' => [],
            'pieces' => [],
        ]);
    }

    public function getHeading(): string | Htmlable
    {
        return 'Cotizador comercial · Acrílicos Serna 2026';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Una cotización puede tener varias piezas; cada pieza agrupa sus materiales e ítems. IVA parametrizado; retefuente según cliente retenedor.';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make([
                    'default' => 1,
                    'lg' => 12,
                ])
                    ->extraAttributes(['class' => 'fi-cotizador-acrilico-layout'])
                    ->schema([
                        Section::make('Encabezado comercial')
                            ->description('Campos de la cotización tipo Acrílicos Serna (proyectos especiales).')
                            ->columnSpan(['lg' => 8])
                            ->columns(2)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Empresa / Cliente')
                                    ->options(fn () => Customer::query()->where('is_active', true)->orderBy('name')->get()
                                        ->mapWithKeys(fn (Customer $c) => [
                                            $c->id => $c->displayName()
                                                .(filled($c->tax_id) ? " · {$c->tax_id}" : '')
                                                .($c->is_retenedor ? ' · Retenedor '.$c->retenedor_percent.'%' : ''),
                                        ]))
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            $set('withholding_rate', 0);

                                            return;
                                        }

                                        $customer = Customer::query()->find($state);
                                        $set('withholding_rate', $customer?->withholdingRate() ?? 0);
                                        $set('lead_id', null);
                                        $set('contact_name', $customer?->personContactName());
                                    }),
                                Select::make('lead_id')
                                    ->label('Prospecto')
                                    ->options(fn () => Lead::query()->orderBy('name')->get()
                                        ->mapWithKeys(fn (Lead $lead) => [
                                            $lead->id => filled($lead->company)
                                                ? "{$lead->name} ({$lead->company})"
                                                : (string) $lead->name,
                                        ]))
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(function (?int $state, Set $set): void {
                                        if (! $state) {
                                            return;
                                        }
                                        $lead = Lead::query()->find($state);
                                        $set('customer_id', null);
                                        $set('withholding_rate', 0);
                                        $set('contact_name', $lead?->name);
                                    }),
                                TextInput::make('project_name')
                                    ->label('Proyecto')
                                    ->placeholder('PROYECTO MEDELLIN')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 400)
                                    ->columnSpanFull()
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                        $pieces = $get('pieces') ?? [];
                                        if (! is_array($pieces) || count($pieces) !== 1) {
                                            return;
                                        }

                                        $firstKey = array_key_first($pieces);
                                        if ($firstKey === null) {
                                            return;
                                        }

                                        $currentName = trim((string) ($pieces[$firstKey]['name'] ?? ''));
                                        if ($currentName === '' || $currentName === mb_strtoupper(trim((string) ($state ?? '')), 'UTF-8')) {
                                            $pieces[$firstKey]['name'] = mb_strtoupper(trim((string) ($state ?? '')), 'UTF-8');
                                            $set('pieces', $pieces);
                                        }
                                    }),
                                TextInput::make('contact_name')
                                    ->label('Contacto')
                                    ->maxLength(255)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('payment_form')
                                    ->label('Forma de pago')
                                    ->placeholder('CONTADO')
                                    ->datalist(['CONTADO', '50% ANTICIPO', 'TRANSFERENCIA', 'CREDITO'])
                                    ->default('CONTADO')
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('validity_days')
                                    ->label('Vigencia (días)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(10)
                                    ->required()
                                    ->live(debounce: 400)
                                    ->helperText('Al cambiar los días se recalcula la fecha «Válida hasta».')
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        $days = max(1, (int) ($state ?: 10));
                                        $set('valid_until', now()->addDays($days)->toDateString());

                                        $terms = (string) ($get('terms') ?? '');
                                        $daysLabel = $days === 1 ? '1 día calendario' : "{$days} días calendario";
                                        $replacement = "Vigencia de la cotización: {$daysLabel}.";
                                        if (preg_match('/Vigencia de la cotización:[^\n]*/u', $terms)) {
                                            $set('terms', preg_replace('/Vigencia de la cotización:[^\n]*/u', $replacement, $terms, 1));
                                        }
                                    }),
                                DatePicker::make('valid_until')
                                    ->label('Válida hasta')
                                    ->native(false)
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('Se calcula sola: hoy + vigencia en días.'),
                                DatePicker::make('delivery_date')
                                    ->label('Fecha de entrega')
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set): void {
                                        if (filled($state)) {
                                            $set('delivery_note', null);
                                        } else {
                                            $set('delivery_note', 'A CONVENIR');
                                        }
                                    }),
                                TextInput::make('delivery_note')
                                    ->label('Entrega (texto)')
                                    ->helperText('Si no hay fecha, se imprime este texto (ej. A CONVENIR).')
                                    ->default('A CONVENIR')
                                    ->maxLength(100)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                TextInput::make('advance_percent')
                                    ->label('Anticipo %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->default(50),
                                TextInput::make('withholding_rate')
                                    ->label('Retefuente %')
                                    ->helperText('Se toma del cliente si es retenedor; puedes ajustarlo aquí.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->live(debounce: 400),
                                Textarea::make('notes')
                                    ->label('Observaciones')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Textarea::make('terms')
                                    ->label('Forma de pago / condiciones')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Resumen financiero')
                            ->columnSpan(['lg' => 4])
                            ->schema([
                                Placeholder::make('breakdown')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): HtmlString => $this->breakdownHtml($get))
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Asistente')
                            ->icon('heroicon-o-sparkles')
                            ->columnSpanFull()
                            ->collapsed()
                            ->schema([
                                Textarea::make('ai_prompt')
                                    ->label('Escribe al asistente')
                                    ->placeholder(
                                        "Proyecto Fachada Centro. Contacto Ana Pérez. Contado.\n"
                                        ."Pieza 1: Aviso acrílico 3mm 200x80 cm cristal, corte láser, 10 letras 3D 15x80 cm rectas sin tapa, vinilo instalado, LED perimetral + transporte e instalación por 850000.\n"
                                        ."Pieza 2: Lámina entera 3mm 120x180 color, enchapado 50x30 cm, acabado espejo plata 40x20 cm, plotter 30x20 cm y mano de obra 3mm 60x40 cm.\n"
                                        .'Pieza 3: Cubrealfombra presidente, cuna acrílica 4mm, cono acrílico P.D. 15.5, plantilla extra y backlight LED.'
                                    )
                                    ->rows(7)
                                    ->columnSpanFull(),
                                Actions::make([
                                    Action::make('applyAiDraft')
                                        ->label('Generar')
                                        ->icon('heroicon-o-sparkles')
                                        ->color('primary')
                                        ->action('applyAiDraft'),
                                    Action::make('replaceAiDraft')
                                        ->label('Reemplazar todo')
                                        ->icon('heroicon-o-arrow-path')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->modalHeading('¿Reemplazar la cotización actual?')
                                        ->modalDescription('Se eliminarán las piezas e ítems actuales y se cargará solo lo que genere el asistente.')
                                        ->modalSubmitActionLabel('Sí, reemplazar')
                                        ->action('replaceAiDraft'),
                                ])->columnSpanFull(),
                                Placeholder::make('quote_blockers_display')
                                    ->hiddenLabel()
                                    ->visible(fn (Get $get): bool => collect($get('quote_blockers') ?? [])
                                        ->contains(fn ($row): bool => filled(trim((string) $row))))
                                    ->content(function (Get $get): HtmlString {
                                        $blockers = array_values(array_filter(
                                            $get('quote_blockers') ?? [],
                                            fn ($row): bool => filled(trim((string) $row)),
                                        ));

                                        $lis = collect($blockers)
                                            ->map(fn ($row): string => '<li>'.e((string) $row).'</li>')
                                            ->implode('');

                                        return new HtmlString(
                                            '<div style="border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:.5rem;padding:.75rem 1rem;">'
                                            .'<strong>Hay que corregir esto antes de crear la cotización:</strong>'
                                            .'<ul style="margin:.5rem 0 0 1.1rem;padding:0;">'.$lis.'</ul>'
                                            .'</div>'
                                        );
                                    })
                                    ->columnSpanFull(),
                                Placeholder::make('ai_explanation_display')
                                    ->label('Respuesta del asistente')
                                    ->visible(fn (Get $get): bool => filled($get('ai_explanation')))
                                    ->content(fn (Get $get): HtmlString => new HtmlString(
                                        '<div style="white-space:pre-wrap;font-size:.9rem;line-height:1.45;border:1px solid #e5e7eb;background:#f9fafb;border-radius:.75rem;padding:.85rem 1rem;">'
                                        .e((string) $get('ai_explanation'))
                                        .'</div>'
                                    ))
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Piezas de la cotización')
                            ->description('Cada pieza agrupa varios ítems (material, acabados, instalación…). Puedes agregar varias piezas en la misma cotización.')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('pieces')
                                    ->label('Piezas')
                                    ->live(debounce: 400)
                                    ->extraAttributes(['class' => 'fi-cotizador-pieces-zebra'])
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Pieza')
                                            ->placeholder('PROYECTO MEDELLIN / AVISO FACHADA')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(debounce: 400)
                                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                            ->columnSpanFull(),
                                        Repeater::make('items')
                                            ->label('Ítems de esta pieza')
                                            ->live(debounce: 400)
                                            ->schema($this->itemFields())
                                            ->columns(6)
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar ítem a la pieza')
                                            ->cloneable()
                                            ->collapsible()
                                            ->extraAttributes(['class' => 'fi-cotizador-items-zebra'])
                                            ->itemLabel(function (array $state): ?string {
                                                $material = $state['material'] ?? null;
                                                $type = $this->resolveItemType($state['item_type'] ?? null);

                                                return $material ?: $type?->getLabel() ?: 'Ítem';
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('Agregar pieza')
                                    ->cloneable()
                                    ->collapsible()
                                    ->truncateItemLabel(false)
                                    ->itemLabel(fn (array $state, mixed $schema = null) => SernaItemFormFields::pieceLabel($state, $schema)),
                            ]),
                        Actions::make([
                            Action::make('saveIncompleteDraft')
                                ->label('Guardar borrador incompleto')
                                ->icon('heroicon-o-document')
                                ->color('gray')
                                ->action('saveIncompleteDraft'),
                            Action::make('createQuote')
                                ->label('Crear cotización')
                                ->icon('heroicon-o-document-plus')
                                ->color('primary')
                                ->action('createQuote'),
                        ])
                            ->columnSpanFull()
                            ->alignEnd(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('createQuote'),
            ]);
    }

    public function applyAiDraft(): void
    {
        // Conserva lo existente y suma lo que interprete la IA.
        $existing = $this->collectExistingPieces($this->data ?? []);
        $this->runAiDraft(append: $existing !== [], forceReplace: false);
    }

    public function replaceAiDraft(): void
    {
        $this->runAiDraft(append: false, forceReplace: true);
    }

    private function runAiDraft(bool $append, bool $forceReplace = false): void
    {
        $state = $this->data ?? [];
        $prompt = trim((string) ($state['ai_prompt'] ?? ''));
        $existingPieces = $forceReplace ? [] : $this->collectExistingPieces($state);
        $shouldAppend = $append || $existingPieces !== [];

        try {
            $draft = app(SernaAiAssistant::class)->draftFromText(
                $prompt,
                existingPieces: $shouldAppend ? $existingPieces : null,
                append: $shouldAppend,
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->title($forceReplace ? 'No se pudo reemplazar la cotización' : 'No se pudo aplicar con IA')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (($draft['pieces'] ?? []) === []) {
            Notification::make()
                ->title('No se detectaron piezas')
                ->body('Intenta ser más específico: pieza, material, medidas X×Y, LED/backlight y cantidad.')
                ->warning()
                ->send();

            return;
        }

        $blockers = array_values(array_unique(array_filter(
            $draft['unresolved'] ?? data_get($draft, 'readiness.errors', []),
            fn ($row): bool => filled(trim((string) $row)),
        )));

        $this->form->fill([
            ...$state,
            'project_name' => $draft['project_name'] ?: ($state['project_name'] ?? null),
            'contact_name' => $draft['contact_name'] ?: ($state['contact_name'] ?? null),
            'payment_form' => $draft['payment_form'] ?: ($state['payment_form'] ?? 'CONTADO'),
            'notes' => $draft['notes'] ?: ($state['notes'] ?? null),
            'pieces' => $draft['pieces'],
            'ai_prompt' => $prompt,
            'quote_blockers' => $blockers,
            'ai_explanation' => $draft['explanation']
                ?? ($forceReplace
                    ? 'Cotización reemplazada por el borrador IA. Revisa medidas, luces/fuentes y tarifas.'
                    : ($shouldAppend
                        ? 'Se conservaron tus ítems previos y se agregó lo nuevo. Revisa luces, fuentes, medidas y tarifas.'
                        : 'Borrador aplicado ('.$draft['source'].'). Revisa medidas, luces/fuentes y tarifas antes de crear.')),
        ]);

        $pricingOk = (bool) data_get($draft, 'pricing.ok', false);
        $subtotal = data_get($draft, 'pricing.subtotal');

        if ($blockers !== []) {
            Notification::make()
                ->title('Hay elementos no reconocidos o incompletos')
                ->body(implode(' · ', array_slice($blockers, 0, 3))
                    .(count($blockers) > 3 ? '…' : '')
                    .' No podrás crear la cotización hasta corregirlos.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($forceReplace
                ? ($pricingOk ? 'Cotización reemplazada' : 'Reemplazo parcial')
                : ($pricingOk ? 'IA aplicada' : 'IA aplicada (parcial)'))
            ->body(
                count($draft['pieces']).' pieza(s) · '.$draft['source']
                .($pricingOk && $subtotal !== null ? ' · subtotal '.Money::format($subtotal) : '')
            )
            ->success()
            ->send();
    }

    /**
     * Piezas actuales a conservar al generar/agregar con IA.
     *
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    private function collectExistingPieces(array $state): array
    {
        $pieces = [];
        $index = 0;

        foreach ($state['pieces'] ?? [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $items = array_values(array_filter(
                $piece['items'] ?? [],
                fn ($item): bool => is_array($item) && filled($item['item_type'] ?? null),
            ));

            if ($items === []) {
                continue;
            }

            // Ignora el ítem default vacío (solo plantilla del formulario).
            if ($this->isDefaultPlaceholderPiece($piece, $items)) {
                continue;
            }

            $index++;
            $name = mb_strtoupper(trim((string) ($piece['name'] ?? '')), 'UTF-8');
            if ($name === '') {
                $name = 'PIEZA '.$index;
            }

            $pieces[] = [
                'name' => $name,
                'items' => $items,
            ];
        }

        return $pieces;
    }

    /**
     * @param  array<string, mixed>  $piece
     * @param  list<array<string, mixed>>  $items
     */
    private function isDefaultPlaceholderPiece(array $piece, array $items): bool
    {
        if (filled(trim((string) ($piece['name'] ?? '')))) {
            return false;
        }

        if (count($items) !== 1) {
            return false;
        }

        $item = $items[0];
        $type = $this->itemTypeValue($item['item_type'] ?? null);
        $material = mb_strtoupper(trim((string) ($item['material'] ?? '')), 'UTF-8');
        $acabados = trim((string) ($item['acabados'] ?? ''));

        return $type === SernaItemType::CorteLaser->value
            && $material === 'ACRILICO 3MM'
            && $acabados === ''
            && (float) ($item['width_cm'] ?? 0) === 30.0
            && (float) ($item['height_cm'] ?? 0) === 20.0
            && blank($item['process_rate_id'] ?? null)
            && blank($item['lighting_option_id'] ?? null)
            && blank($item['unit_price'] ?? null);
    }

    public function improveAcabadosAi(): void
    {
        $state = $this->data ?? [];
        $pieces = array_values(array_filter($state['pieces'] ?? [], fn ($p): bool => is_array($p)));

        try {
            $improved = app(SernaAiAssistant::class)->improveAcabados(
                $pieces,
                $state['project_name'] ?? null,
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->title('No se pudieron mejorar los acabados')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->form->fill([
            ...$state,
            'pieces' => $improved,
            'ai_explanation' => 'Acabados actualizados. Revisa el tono comercial antes de enviar al cliente.',
        ]);

        Notification::make()
            ->title('Acabados mejorados')
            ->success()
            ->send();
    }

    public function explainPriceAi(): void
    {
        $state = $this->data ?? [];
        $pieces = $this->normalizePiecesForEngine($state);

        try {
            $explanation = app(SernaAiAssistant::class)->explainPrice(
                $pieces,
                (float) ($state['withholding_rate'] ?? 0),
                $state['project_name'] ?? null,
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->title('No se pudo explicar el precio')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->form->fill([
            ...$state,
            'ai_explanation' => $explanation,
        ]);

        Notification::make()
            ->title('Explicación lista')
            ->success()
            ->send();
    }

    public function saveIncompleteDraft(): void
    {
        $this->persistQuote(allowIncomplete: true);
    }

    public function createQuote(): void
    {
        $this->persistQuote(allowIncomplete: false);
    }

    private function persistQuote(bool $allowIncomplete): void
    {
        if ($allowIncomplete) {
            $raw = is_array($this->data) ? $this->data : [];
        } else {
            try {
                $raw = $this->form->getState();
            } catch (ValidationException $exception) {
                throw $exception;
            }
        }

        if (blank($raw['customer_id'] ?? null) && blank($raw['lead_id'] ?? null)) {
            Notification::make()
                ->title('Selecciona un cliente o prospecto')
                ->danger()
                ->send();

            return;
        }

        $raw['pieces'] = $this->normalizePiecesPayload($raw['pieces'] ?? []);
        $prompt = trim((string) ($raw['ai_prompt'] ?? ''));
        $readiness = app(SernaQuoteReadinessChecker::class)->check(
            $raw['pieces'],
            $prompt !== '' ? $prompt : null,
        );

        $this->form->fill([
            ...($this->data ?? []),
            ...$raw,
            'quote_blockers' => $readiness['errors'],
        ]);

        if (! $allowIncomplete && ! $readiness['ok']) {
            Notification::make()
                ->title('No se puede crear la cotización')
                ->body(
                    implode(' · ', array_slice($readiness['errors'], 0, 4))
                    .(count($readiness['errors']) > 4 ? '…' : '')
                    .' Usa «Guardar borrador incompleto» si quieres continuar y completar después.'
                )
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($raw['pieces'] === []) {
            Notification::make()
                ->title('Agrega al menos una pieza con ítems')
                ->danger()
                ->send();

            return;
        }

        $rules = (new CalculateSernaQuoteRequest)->rules();
        if ($allowIncomplete) {
            $rules['project_name'] = ['nullable', 'string', 'max:255'];
            $rules['pieces.*.name'] = ['nullable', 'string', 'max:255'];
        }

        try {
            $validated = Validator::make(
                $raw,
                $rules,
                (new CalculateSernaQuoteRequest)->messages(),
                (new CalculateSernaQuoteRequest)->attributes(),
            )->validate();

            $validated['ai_prompt'] = $prompt !== '' ? $prompt : null;

            $quote = app(CreateQuoteFromSernaCalculation::class)->handle(
                $validated,
                allowIncomplete: $allowIncomplete,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title($allowIncomplete ? 'No se pudo guardar el borrador' : 'Revisa los datos de la propuesta')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Datos incompletos.')
                ->danger()
                ->send();

            if (! $allowIncomplete) {
                throw $exception;
            }

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title($allowIncomplete ? 'No se pudo guardar el borrador' : 'No se pudo crear la cotización')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($allowIncomplete ? 'Borrador incompleto guardado' : 'Cotización creada')
            ->body(
                $quote->code
                .($allowIncomplete && $readiness['errors'] !== []
                    ? ' · pendientes: '.count($readiness['errors'])
                    : '')
            )
            ->success()
            ->send();

        $this->redirect(QuoteResource::getUrl('edit', ['record' => $quote]), navigate: true);
    }

    /**
     * @param  mixed  $pieces
     * @return list<array<string, mixed>>
     */
    private function normalizePiecesPayload(mixed $pieces): array
    {
        if (! is_array($pieces)) {
            return [];
        }

        return array_values(array_map(function (array $piece): array {
            $piece['name'] = mb_strtoupper(trim((string) ($piece['name'] ?? '')), 'UTF-8');
            $piece['items'] = array_values(array_map(function (array $row): array {
                $row['item_type'] = $this->itemTypeValue($row['item_type'] ?? null);
                if (array_key_exists('quantity', $row) && blank($row['quantity'])) {
                    $row['quantity'] = 1;
                }

                return $row;
            }, array_values(array_filter(
                $piece['items'] ?? [],
                fn ($row): bool => is_array($row) && filled($this->itemTypeValue($row['item_type'] ?? null)),
            ))));

            return $piece;
        }, array_values(array_filter(
            $pieces,
            fn ($row): bool => is_array($row),
        ))));
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    private function itemFields(): array
    {
        return SernaItemFormFields::make();
    }

    private function resolveItemType(mixed $type): ?SernaItemType
    {
        return SernaItemFormFields::resolveItemType($type);
    }

    private function itemTypeValue(mixed $type): ?string
    {
        return SernaItemFormFields::itemTypeValue($type);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array{name: string, items: list<array<string, mixed>>}>
     */
    private function normalizePiecesForEngine(array $state): array
    {
        $pieces = [];

        foreach ($state['pieces'] ?? [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }

            $items = array_values(array_map(
                function (array $row): array {
                    $row['item_type'] = $this->itemTypeValue($row['item_type'] ?? null);

                    return $row;
                },
                array_values(array_filter(
                    $piece['items'] ?? [],
                    fn ($row): bool => is_array($row) && $this->resolveItemType($row['item_type'] ?? null) !== null,
                )),
            ));

            if ($items === []) {
                continue;
            }

            $name = trim((string) ($piece['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($state['project_name'] ?? 'PIEZA'));
            }

            $pieces[] = [
                'name' => $name,
                'items' => $items,
            ];
        }

        return $pieces;
    }

    private function breakdownHtml(Get $get): HtmlString
    {
        try {
            $pieces = $this->normalizePiecesForEngine([
                'project_name' => $get('project_name'),
                'pieces' => $get('pieces') ?? [],
            ]);

            if ($pieces === []) {
                return new HtmlString(view('filament.pages.partials.serna-quote-breakdown', [
                    'result' => null,
                    'error' => null,
                ])->render());
            }

            $result = app(SernaQuotationEngine::class)->calculateProposalFromPieces(
                $pieces,
                (float) ($get('withholding_rate') ?? 0),
            );

            return new HtmlString(view('filament.pages.partials.serna-quote-breakdown', [
                'result' => $result,
                'error' => null,
            ])->render());
        } catch (Throwable $exception) {
            return new HtmlString(view('filament.pages.partials.serna-quote-breakdown', [
                'result' => null,
                'error' => $exception->getMessage(),
            ])->render());
        }
    }
}
