<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Entregas;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use App\Support\CommercialScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingDeliveriesTable extends TableWidget
{
    protected static ?string $heading = 'Pendientes de entrega';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null || $user->isOperario()) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:Entregas')
            || $user->can('ViewAny:Sale');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => CommercialScope::constrain(
                    Sale::query()
                        ->with(['customer', 'productionOrders'])
                        ->readyForDelivery()
                        ->latest('sold_at')
                        ->limit(8)
                )
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('code')
                    ->label('Factura')
                    ->weight('bold'),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('—'),
                TextColumn::make('delivery_progress')
                    ->label('Progreso')
                    ->badge()
                    ->state(fn (Sale $record): string => $record->deliveryProgressLabel())
                    ->color('success'),
                TextColumn::make('due_at')
                    ->label('Pactada')
                    ->state(fn (Sale $record) => $record->deliveryDueAt())
                    ->date('d/m/Y')
                    ->color(fn (Sale $record): ?string => $record->isDeliveryOverdue() ? 'danger' : null),
            ])
            ->recordUrl(fn (Sale $record): string => SaleResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                \Filament\Actions\Action::make('all')
                    ->label('Ver entregas')
                    ->url(Entregas::getUrl())
                    ->visible(fn (): bool => Entregas::canAccess()),
            ])
            ->emptyStateHeading('Sin entregas pendientes')
            ->emptyStateDescription('Facturas de stock o con OPs listas aparecen aquí.');
    }
}
