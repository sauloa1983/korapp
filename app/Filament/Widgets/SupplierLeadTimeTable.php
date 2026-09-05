<?php

namespace App\Filament\Widgets;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierLeadTimeTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user !== null && $user->hasRole('super_admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Desempeño de proveedores (compras recibidas)')
            ->query(
                fn (): Builder => Supplier::query()
                    ->whereHas('purchases', fn (Builder $q) => $q->where('status', PurchaseStatus::Recibida))
            )
            ->emptyStateHeading('Sin compras recibidas')
            ->emptyStateDescription('Aún no se han recibido compras para calcular el desempeño.')
            ->columns([
                TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('received_count')
                    ->label('Compras')
                    ->badge()
                    ->state(fn (Supplier $record): int => $record->purchases()
                        ->where('status', PurchaseStatus::Recibida)
                        ->count()),
                TextColumn::make('total_spend')
                    ->label('Gasto total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->state(fn (Supplier $record): float => (float) $record->purchases()
                        ->where('status', PurchaseStatus::Recibida)
                        ->sum('total')),
                TextColumn::make('avg_lead_time')
                    ->label('Tiempo de entrega prom.')
                    ->badge()
                    ->color('info')
                    ->state(function (Supplier $record): string {
                        $purchases = $record->purchases()
                            ->where('status', PurchaseStatus::Recibida)
                            ->whereNotNull('received_at')
                            ->whereNotNull('ordered_at')
                            ->get();

                        if ($purchases->isEmpty()) {
                            return '—';
                        }

                        $avg = $purchases->avg(fn (Purchase $p): int => (int) $p->ordered_at->diffInDays($p->received_at));

                        return round($avg, 1).' días';
                    }),
            ]);
    }
}
