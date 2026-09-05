<?php

namespace App\Filament\Widgets;

use App\Enums\VisitStatus;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingVisitsTable extends TableWidget
{
    protected static ?string $heading = 'Próximas visitas y seguimientos';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->can('ViewAny:Visit')
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Visit::query()
                    ->with(['lead', 'customer', 'user'])
                    ->where(function (Builder $query): void {
                        $query
                            ->where(function (Builder $open): void {
                                $open->where('status', VisitStatus::Programada)
                                    ->where('scheduled_at', '>=', now()->subDay());
                            })
                            ->orWhere(function (Builder $followUp): void {
                                $followUp->whereNotNull('next_follow_up_at')
                                    ->whereBetween('next_follow_up_at', [now()->subDay(), now()->addDays(14)]);
                            });
                    })
                    ->orderByRaw('COALESCE(next_follow_up_at, scheduled_at) asc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('contact')
                    ->label('Contacto')
                    ->state(fn (Visit $record): string => $record->contactName()),
                TextColumn::make('next_follow_up_at')
                    ->label('Próx. seguimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->color(fn (Visit $record): ?string => $record->next_follow_up_at?->isPast() ? 'danger' : null),
            ])
            ->recordUrl(fn (Visit $record): string => VisitResource::getUrl('edit', ['record' => $record]))
            ->paginated(false)
            ->emptyStateHeading('Sin visitas próximas')
            ->emptyStateDescription('Cuando agendas seguimientos aparecerán aquí.');
    }
}
