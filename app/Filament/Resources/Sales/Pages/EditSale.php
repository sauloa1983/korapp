<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class EditSale extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SaleResource::class;

    public function getTitle(): string | Htmlable
    {
        $sale = $this->getRecord();

        if (! $sale->isEditable()) {
            return 'Venta '.$sale->code.' (solo lectura)';
        }

        return parent::getTitle();
    }

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Sale $record */
        if (! $record->isEditable()) {
            Notification::make()
                ->title('Venta no editable')
                ->body('Confirma o reversa la venta según corresponda. Las confirmadas no se modifican.')
                ->warning()
                ->send();

            return $record;
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reverse')
                ->label('Reversar')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reversar venta')
                ->modalDescription('La venta vuelve a borrador para poder editarla. Se conserva el número de factura.')
                ->visible(fn (): bool => $this->getRecord()->status === \App\Enums\SaleStatus::Confirmada)
                ->action(function (): void {
                    try {
                        $this->getRecord()->reverse();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()
                            ->title('No se pudo reversar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Venta reversada')
                        ->body('Quedó en borrador. Ya puedes editarla.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                    $this->redirect(SaleResource::getUrl('edit', ['record' => $this->getRecord()]));
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->isEditable()),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
