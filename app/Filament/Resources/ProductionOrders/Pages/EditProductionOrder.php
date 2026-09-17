<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\ProductionOrders\Support\OrderCodeModal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProductionOrder extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProductionOrderResource::class;

    protected function resolveRecord(int | string $key): \Illuminate\Database\Eloquent\Model
    {
        return parent::resolveRecord($key)->loadMissing([
            'sale.customer',
            'quote.customer',
            'quote.lead',
            'quotedBy',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markDelivered')
                ->label('Entrega final')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('received_by')
                        ->label('Recibido por')
                        ->placeholder('Nombre de quien recibe')
                        ->maxLength(120),
                ])
                ->modalHeading('Registrar entrega')
                ->modalDescription('Flujo: Cotización → OP → Venta → Entrega. Confirma que el cliente ya recibió el trabajo.')
                ->visible(fn (): bool => $this->getRecord()->isReadyForDelivery())
                ->action(function (array $data): void {
                    try {
                        $this->getRecord()->markDelivered($data['received_by'] ?? null);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('No se pudo entregar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }
                    Notification::make()
                        ->title('Entrega registrada')
                        ->success()
                        ->send();
                }),
            Action::make('codes')
                ->label('QR / Barras')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->modalSubmitAction(false)
                ->modalHeading(fn (): string => 'Códigos · '.$this->getRecord()->code)
                ->modalContent(fn () => OrderCodeModal::content($this->getRecord())),
            Action::make('pdf')
                ->label('Imprimir OP')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->url(fn (): string => route('orders.pdf', $this->getRecord()))
                ->openUrlInNewTab(),
            Action::make('labels')
                ->label('Imprimir etiquetas')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('orders.labels', $this->getRecord()))
                ->openUrlInNewTab(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
