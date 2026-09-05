<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Quotes\Actions\CreateProductionOrderFromQuoteAction;
use App\Filament\Resources\Quotes\Actions\CreateSaleFromQuoteAction;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditQuote extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = QuoteResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::None;
    }

    public function getTitle(): string | Htmlable
    {
        $quote = $this->getRecord();

        if (! $quote->isEditable()) {
            return 'Cotización '.$quote->code.' (solo lectura)';
        }

        return parent::getTitle();
    }

    /**
     * Permite abrir cotizaciones aceptadas en modo consulta (view), no solo editar.
     */
    protected function authorizeAccess(): void
    {
        abort_unless(
            Gate::allows('view', $this->getRecord()) || Gate::allows('update', $this->getRecord()),
            403
        );
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled(fn (): bool => ! $this->getRecord()->isEditable());
    }

    /**
     * @return array<Action|\Filament\Actions\ActionGroup>
     */
    protected function getFormActions(): array
    {
        if (! $this->getRecord()->isEditable()) {
            return [];
        }

        return parent::getFormActions();
    }

    /**
     * @param  Quote  $record
     */
    protected function resolveRecord(int | string $key): Model
    {
        /** @var Quote $record */
        $record = parent::resolveRecord($key);
        $record->load(['pieces.items.item', 'items.item', 'customer', 'lead']);

        // Cotizaciones antiguas sin piezas: agrupa líneas en una pieza para editar como el cotizador.
        if ($record->isEditable() && $record->pieces->isEmpty() && $record->items->isNotEmpty()) {
            $piece = $record->pieces()->create([
                'name' => mb_strtoupper(trim((string) ($record->project_name ?: 'PIEZA 1')), 'UTF-8'),
                'sort_order' => 0,
            ]);
            $record->items()
                ->whereNull('quote_piece_id')
                ->update(['quote_piece_id' => $piece->id]);
            $record->load(['pieces.items.item']);
        }

        return $record;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Quote $record */
        if (! $record->isEditable()) {
            Notification::make()
                ->title('Cotización aceptada')
                ->body('No se puede modificar una cotización ya aceptada.')
                ->warning()
                ->send();

            return $record;
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        if (! $this->getRecord()->isEditable()) {
            return;
        }

        $quote = $this->getRecord();
        $quote->load('pieces.items');

        foreach ($quote->pieces as $pieceIndex => $piece) {
            if ((int) $piece->sort_order !== $pieceIndex) {
                $piece->update(['sort_order' => $pieceIndex]);
            }

            foreach ($piece->items as $item) {
                $meta = is_array($item->meta) ? $item->meta : [];
                $calc = is_array($meta['calculation'] ?? null) ? $meta['calculation'] : [];
                $needsPieceSync = ($meta['piece_name'] ?? null) !== $piece->name
                    || ($calc['pieza'] ?? null) !== $piece->name;

                if (! $needsPieceSync) {
                    continue;
                }

                $meta['piece_name'] = $piece->name;
                $calc['pieza'] = $piece->name;
                $calc['piece_name'] = $piece->name;
                $meta['calculation'] = $calc;

                $pending = ! empty($meta['incomplete']) || ! empty($calc['incomplete']);
                $material = trim((string) ($calc['material'] ?? ''));
                $acabados = trim((string) ($calc['acabados'] ?? ''));
                $parts = array_values(array_filter([
                    ($pending ? '[PENDIENTE] ' : '').$piece->name,
                    $material !== '' ? 'Material: '.$material : null,
                    $acabados !== '' ? 'Acabados: '.$acabados : null,
                ]));

                $item->update([
                    'meta' => $meta,
                    'description' => $parts !== []
                        ? mb_substr(implode(' · ', $parts), 0, 2000, 'UTF-8')
                        : $item->description,
                ]);
            }
        }

        $quote->recalculateTotal();
        $this->refreshFormData([
            'subtotal',
            'iva_rate',
            'iva_amount',
            'withholding_rate',
            'withholding_amount',
            'total',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('quotes.pdf', $this->getRecord()))
                ->openUrlInNewTab(),
            CreateProductionOrderFromQuoteAction::make()
                ->visible(fn (): bool => $this->getRecord()->canCreateProductionOrders()),
            Action::make('viewOrders')
                ->label('Ver OP')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->productionOrders()->exists())
                ->url(fn (): string => ProductionOrderResource::getUrl('index'))
                ->openUrlInNewTab(),
            CreateSaleFromQuoteAction::make()
                ->visible(fn (): bool => $this->getRecord()->canCreateSale()),
            Action::make('viewSale')
                ->label('Ver venta')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->sale()->exists())
                ->url(fn (): string => SaleResource::getUrl('edit', ['record' => $this->getRecord()->sale]))
                ->openUrlInNewTab(),
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->isEditable()),
        ];
    }
}
