<?php

namespace App\Filament\Widgets;

use App\Enums\ProductionLogStatus;
use App\Models\ProductionLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OperatorRecentJobsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Mis últimos trabajos';

    protected int | string | array $columnSpan = 'full';

    protected int $defaultPaginationPageOption = 8;

    public static function canView(): bool
    {
        return auth()->user()?->isOperario() === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => ProductionLog::query()
                    ->with(['process', 'productionOrder'])
                    ->where('user_id', Auth::id())
                    ->where('status', ProductionLogStatus::Terminado)
                    ->whereNotNull('ended_at')
                    ->latest('ended_at')
            )
            ->defaultSort('ended_at', 'desc')
            ->paginated([8])
            ->emptyStateHeading('Aún no has terminado etapas')
            ->emptyStateDescription('Escanea un QR para iniciar y otro para finalizar.')
            ->columns([
                TextColumn::make('ended_at')
                    ->label('Fin')
                    ->dateTime('d/m H:i')
                    ->sortable(),
                TextColumn::make('productionOrder.code')
                    ->label('OP')
                    ->weight('bold'),
                TextColumn::make('process.name')
                    ->label('Etapa'),
                TextColumn::make('duration_for_humans')
                    ->label('Tiempo')
                    ->badge()
                    ->color('success'),
            ]);
    }
}
