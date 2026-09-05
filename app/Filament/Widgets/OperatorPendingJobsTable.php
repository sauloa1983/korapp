<?php

namespace App\Filament\Widgets;

use App\Enums\ProductionLogStatus;
use App\Enums\ProductionOrderStatus;
use App\Filament\Pages\EscaneoOperario;
use App\Models\ProductionLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OperatorPendingJobsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Trabajos pendientes';

    protected int | string | array $columnSpan = 'full';

    protected int $defaultPaginationPageOption = 8;

    public static function canView(): bool
    {
        return auth()->user()?->isOperario() === true;
    }

    public function table(Table $table): Table
    {
        $userId = Auth::id();

        return $table
            ->query(
                fn (): Builder => ProductionLog::query()
                    ->with(['process', 'productionOrder', 'user'])
                    ->whereHas('productionOrder', fn (Builder $q) => $q->whereIn('status', [
                        ProductionOrderStatus::Pendiente->value,
                        ProductionOrderStatus::EnProgreso->value,
                    ]))
                    ->where(function (Builder $q) use ($userId): void {
                        $q->where('status', ProductionLogStatus::EnEspera)
                            ->orWhere(function (Builder $q2) use ($userId): void {
                                $q2->where('status', ProductionLogStatus::Procesando)
                                    ->where(fn (Builder $q3) => $q3->where('user_id', $userId)->orWhereNull('user_id'));
                            });
                    })
                    ->orderByRaw("CASE WHEN status = 'procesando' THEN 0 ELSE 1 END")
                    ->orderBy('production_order_id')
                    ->orderBy('sequence')
            )
            ->paginated([8])
            ->emptyStateHeading('No hay etapas pendientes')
            ->emptyStateDescription('Cuando haya órdenes en planta, aparecerán aquí.')
            ->columns([
                TextColumn::make('productionOrder.code')
                    ->label('OP')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('process.name')
                    ->label('Etapa')
                    ->searchable(),
                TextColumn::make('sequence')
                    ->label('#')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('user.name')
                    ->label('Asignado')
                    ->placeholder('Libre')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('scan')
                    ->label('Ir a escanear')
                    ->icon('heroicon-o-qr-code')
                    ->url(EscaneoOperario::getUrl()),
            ]);
    }
}
