<?php

namespace App\Filament\Resources\Visits\Pages;

use App\Enums\VisitStatus;
use App\Filament\Resources\Visits\VisitResource;
use App\Models\Visit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class CalendarVisits extends Page
{
    protected static string $resource = VisitResource::class;

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $slug = 'calendario';

    protected string $view = 'filament.resources.visits.pages.calendar-visits';

    /** @var 'month'|'week'|'day' */
    public string $viewMode = 'month';

    /** Fecha ancla del periodo visible (Y-m-d). */
    public string $cursor;

    public function mount(): void
    {
        $this->cursor = now()->toDateString();
        $this->viewMode = 'month';
    }

    public function getTitle(): string | Htmlable
    {
        return 'Calendario comercial';
    }

    public function getHeading(): string | Htmlable
    {
        return 'Calendario comercial';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Cambia entre mes, semana o día. También puedes volver al listado.';
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('Ver listado')
                ->icon('heroicon-o-bars-3-bottom-left')
                ->color('gray')
                ->url(VisitResource::getUrl('index')),
            Action::make('create')
                ->label('Agendar contacto')
                ->icon('heroicon-o-plus')
                ->url(VisitResource::getUrl('create')),
        ];
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['month', 'week', 'day'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function previousPeriod(): void
    {
        $date = $this->cursorCarbon();

        $this->cursor = match ($this->viewMode) {
            'week' => $date->subWeek()->toDateString(),
            'day' => $date->subDay()->toDateString(),
            default => $date->subMonthNoOverflow()->toDateString(),
        };
    }

    public function nextPeriod(): void
    {
        $date = $this->cursorCarbon();

        $this->cursor = match ($this->viewMode) {
            'week' => $date->addWeek()->toDateString(),
            'day' => $date->addDay()->toDateString(),
            default => $date->addMonthNoOverflow()->toDateString(),
        };
    }

    public function goToday(): void
    {
        $this->cursor = now()->toDateString();
    }

    public function cursorCarbon(): Carbon
    {
        return Carbon::parse($this->cursor)->startOfDay();
    }

    public function getPeriodLabelProperty(): string
    {
        $date = $this->cursorCarbon()->locale('es');

        return match ($this->viewMode) {
            'week' => 'Semana del '.$date->copy()->startOfWeek(Carbon::MONDAY)->translatedFormat('d M')
                .' al '.$date->copy()->endOfWeek(Carbon::SUNDAY)->translatedFormat('d M Y'),
            'day' => $date->translatedFormat('l d \d\e F Y'),
            default => $date->translatedFormat('F Y'),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $date = $this->cursorCarbon();

        return match ($this->viewMode) {
            'week' => [
                $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
            'day' => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
            default => [
                $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ],
        };
    }

    /**
     * @return Collection<int, array{date: Carbon, inMonth: bool, isToday: bool, visits: Collection<int, Visit>}>
     */
    public function getDaysProperty(): Collection
    {
        [$gridStart, $gridEnd] = $this->range();
        $anchor = $this->cursorCarbon();
        $byDate = $this->visitsByDate($gridStart, $gridEnd);

        $days = collect();
        $cursor = $gridStart->copy()->startOfDay();
        $end = $gridEnd->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();

            $days->push([
                'date' => $cursor->copy(),
                'inMonth' => $this->viewMode !== 'month' || $cursor->month === $anchor->month,
                'isToday' => $cursor->isToday(),
                'visits' => collect($byDate[$key] ?? []),
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Eventos del día (vista día), ordenados por hora.
     *
     * @return Collection<int, Visit>
     */
    public function getDayVisitsProperty(): Collection
    {
        return $this->days->first()['visits'] ?? collect();
    }

    /**
     * @return array<string, list<Visit>>
     */
    protected function visitsByDate(Carbon $start, Carbon $end): array
    {
        $visits = Visit::query()
            ->with(['lead', 'customer', 'user'])
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereBetween('next_follow_up_at', [$start, $end]);
            })
            ->orderBy('scheduled_at')
            ->get();

        $byDate = [];

        foreach ($visits as $visit) {
            $dateKey = $visit->scheduled_at?->toDateString();

            if ($dateKey) {
                $byDate[$dateKey][] = $visit;
            }

            $followKey = $visit->next_follow_up_at?->toDateString();

            if ($followKey && $followKey !== $dateKey && $followKey >= $start->toDateString() && $followKey <= $end->toDateString()) {
                $byDate[$followKey][] = $visit;
            }
        }

        return $byDate;
    }

    public function eventColor(Visit $visit): string
    {
        return match ($visit->status) {
            VisitStatus::Programada => 'warning',
            VisitStatus::Realizada => 'success',
            VisitStatus::Cancelada => 'gray',
            VisitStatus::NoAsistio => 'danger',
            default => 'primary',
        };
    }

    public function eventLimit(): int
    {
        return match ($this->viewMode) {
            'week' => 8,
            'day' => 100,
            default => 4,
        };
    }
}
