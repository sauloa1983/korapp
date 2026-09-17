<?php

namespace App\Filament\Pages;

use App\Enums\SaleStatus;
use App\Filament\Concerns\HasSalesAccess;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Support\CommercialScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use UnitEnum;

class Entregas extends Page implements HasTable
{
    use HasSalesAccess;
    use HasTabs;
    use InteractsWithTable;

    protected string $view = 'filament.pages.entregas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Entregas';

    protected static ?string $title = 'Entregas';

    protected static ?string $slug = 'entregas';

    protected static ?int $navigationSort = 12;

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:Entregas')
            || $user->can('ViewAny:ProductionOrder')
            || $user->can('ViewAny:Sale');
    }

    public function mount(): void
    {
        $this->loadDefaultActiveTab();
    }

    public function getHeading(): string
    {
        return 'Entregas';
    }

    public function getSubheading(): ?string
    {
        return 'Por factura. Con OP: Cotización → OP → Venta → Entrega. Sin OP (stock): Venta confirmada → Entrega.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->modifyQueryWithActiveTab($this->baseQuery()))
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Factura')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Venta')
                    ->badge(),
                TextColumn::make('delivery_progress')
                    ->label('Progreso')
                    ->badge()
                    ->state(fn (Sale $record): string => $record->deliveryProgressLabel())
                    ->color(fn (Sale $record): string => match (true) {
                        $record->isFullyDelivered() => 'primary',
                        $record->isReadyForDelivery() => 'success',
                        $record->isDeliveryOverdue() => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ops')
                    ->label('OPs')
                    ->state(function (Sale $record): string {
                        $orders = $record->deliveryOrdersQuery()
                            ->orderBy('code')
                            ->pluck('code');

                        return $orders->isEmpty() ? '—' : $orders->implode(', ');
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label('Pactada')
                    ->state(fn (Sale $record) => $record->deliveryDueAt())
                    ->date('d/m/Y')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\ProductionOrder::query()
                                ->select('due_at')
                                ->whereColumn('production_orders.sale_id', 'sales.id')
                                ->whereNotNull('due_at')
                                ->orderBy('due_at')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->color(fn (Sale $record): ?string => $record->isDeliveryOverdue() ? 'danger' : null)
                    ->description(fn (Sale $record): ?string => $record->isDeliveryOverdue() ? 'Atrasada' : null),
                TextColumn::make('delivered_at')
                    ->label('Entregada')
                    ->state(fn (Sale $record) => $record->deliveredAt())
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('delivery_received_by')
                    ->label('')
                    ->state(fn (Sale $record): bool => filled($record->deliveryReceivedByTooltip()))
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-chat-bubble-left-ellipsis' : null)
                    ->color('gray')
                    ->alignCenter()
                    ->tooltip(fn (Sale $record): ?string => $record->deliveryReceivedByTooltip())
                    ->extraAttributes(fn (Sale $record): array => filled($record->deliveryReceivedByTooltip())
                        ? ['class' => 'cursor-help']
                        : []),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('COP', locale: 'es_CO')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('markDelivered')
                    ->label('Entregar')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn (Sale $record): bool => $record->isReadyForDelivery())
                    ->form([
                        TextInput::make('received_by')
                            ->label('Recibido por')
                            ->placeholder('Nombre de quien recibe')
                            ->maxLength(120),
                    ])
                    ->modalHeading(fn (Sale $record): string => 'Entregar '.$record->code)
                    ->modalDescription(function (Sale $record): string {
                        if (! $record->needsProductionDelivery()) {
                            return 'Se registrará la entrega de esta factura de stock (sin OP). El estado de la venta sigue en Confirmada.';
                        }

                        return 'Se marcarán como entregadas todas las OPs listas de esta factura ('.$record->deliveryProgressLabel().').';
                    })
                    ->action(function (Sale $record, array $data): void {
                        try {
                            $count = $record->markReadyOrdersDelivered($data['received_by'] ?? null);
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('No se pudo entregar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $body = $count === 0
                            ? "Factura {$record->code}: entrega de stock registrada."
                            : ($count === 1
                                ? "Factura {$record->code}: 1 OP entregada."
                                : "Factura {$record->code}: {$count} OPs entregadas.");

                        Notification::make()
                            ->title('Entrega registrada')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
                Action::make('openSale')
                    ->label('Ver venta')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Nada pendiente de entrega')
            ->emptyStateDescription('Facturas confirmadas de stock o con OPs listas aparecen aquí.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $base = fn (): Builder => $this->baseQuery();

        return [
            'ready' => Tab::make('Listas para entregar')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->readyForDelivery())
                ->badge(fn (): int => $base()->readyForDelivery()->count())
                ->badgeColor('success'),
            'overdue' => Tab::make('Atrasadas')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->deliveryOverdue())
                ->badge(fn (): int => $base()->deliveryOverdue()->count())
                ->badgeColor('danger'),
            'delivered' => Tab::make('Entregadas')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->fullyDelivered()->latest('updated_at'))
                ->badge(fn (): int => $base()->fullyDelivered()->count())
                ->badgeColor('primary'),
        ];
    }

    protected function baseQuery(): Builder
    {
        return CommercialScope::constrain(
            Sale::query()
                ->with(['customer', 'productionOrders'])
                ->where('status', '!=', SaleStatus::Anulada->value)
                ->where(function (Builder $query): void {
                    $query
                        ->whereHas('productionOrders')
                        ->orWhere(function (Builder $stock): void {
                            $stock
                                ->where('status', SaleStatus::Confirmada->value)
                                ->whereDoesntHave('productionOrders');
                        });
                })
        );
    }
}
