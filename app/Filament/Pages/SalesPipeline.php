<?php

namespace App\Filament\Pages;

use App\Enums\LeadStage;
use App\Filament\Concerns\HasSalesAccess;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class SalesPipeline extends Page
{
    use HasSalesAccess;

    protected string $view = 'filament.pages.sales-pipeline';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Embudo de ventas';

    protected static ?string $title = 'Embudo de ventas';

    protected static ?string $slug = 'pipeline-ventas';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return static::canAccessSalesModule();
    }

    public function getHeading(): string
    {
        return 'Embudo de ventas';
    }

    public function getSubheading(): ?string
    {
        return 'Avanza oportunidades por etapa. Al ganar se crean en Clientes y salen de aquí.';
    }

    /** @return array<string, Collection<int, Lead>> */
    public function getColumnsProperty(): array
    {
        $leads = Lead::query()
            ->open()
            ->with(['user', 'customer'])
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy(fn (Lead $lead): string => $lead->stage->value);

        $columns = [];

        foreach (LeadStage::openCases() as $stage) {
            $columns[$stage->value] = $leads->get($stage->value, collect());
        }

        return $columns;
    }

    /** @return Collection<int, Lead> */
    public function getRecentConversionsProperty(): Collection
    {
        return Lead::query()
            ->converted()
            ->with(['customer', 'user'])
            ->latest('converted_at')
            ->limit(5)
            ->get()
            ->each(function (Lead $lead): void {
                $name = (string) ($lead->customer?->name ?: $lead->name);
                $lead->setAttribute('initials', collect(preg_split('/\s+/', trim($name)) ?: [])
                    ->filter()
                    ->take(2)
                    ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->implode(''));
            });
    }

    public function moveLead(int $leadId, string $stage): void
    {
        $stageEnum = LeadStage::tryFrom($stage);

        if (! $stageEnum) {
            return;
        }

        $lead = Lead::query()->find($leadId);

        if (! $lead) {
            return;
        }

        $this->authorize('update', $lead);

        if ($stageEnum === LeadStage::Won && ! $lead->stage->canMarkWon()) {
            Notification::make()
                ->title('Aún no se puede ganar')
                ->body('Mueve el prospecto a Propuesta o Negociación antes de convertirlo en cliente.')
                ->warning()
                ->send();

            return;
        }

        $existingMatch = $stageEnum === LeadStage::Won
            ? $lead->matchingCustomer()
            : null;

        $lead->moveToStage($stageEnum);
        $lead->refresh()->load('customer');

        if ($stageEnum === LeadStage::Won && $lead->customer_id) {
            $customerName = $lead->customer?->name ?? 'cliente';
            $wasExisting = $existingMatch !== null;

            Notification::make()
                ->title($wasExisting
                    ? "{$lead->name} se vinculó a un cliente existente"
                    : "{$lead->name} se convirtió en cliente")
                ->body($wasExisting
                    ? "No se duplicó. Quedó ligado a {$customerName} y salió del embudo."
                    : "{$customerName} quedó en Clientes y salió del embudo activo.")
                ->success()
                ->actions([
                    Action::make('openCustomer')
                        ->label('Ver cliente')
                        ->url(CustomerResource::getUrl('edit', ['record' => $lead->customer_id])),
                    Action::make('history')
                        ->label('Ver historial')
                        ->url(LeadResource::getUrl('index', ['activeTab' => 'converted'])),
                ])
                ->send();

            return;
        }

        if ($stageEnum === LeadStage::Lost) {
            Notification::make()
                ->title("{$lead->name} marcado como perdido")
                ->body('Sale del embudo. Puedes revisarlo en Prospectos → Perdidos.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title("{$lead->name} → {$stageEnum->getLabel()}")
            ->success()
            ->send();
    }
}
