<?php

namespace App\Filament\Resources\Quotes\Actions;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CreateSaleFromQuoteAction
{
    public static function make(): Action
    {
        return Action::make('createSale')
            ->label('Crear venta')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Crear venta / factura')
            ->modalDescription('Flujo: Cotización → OP → Venta. Todas las OP de planta (una por pieza en Serna) deben estar completadas o entregadas. Transporte/instalación se factura sin OP.')
            ->action(function (mixed $record = null, mixed $livewire = null) {
                $quote = static::resolveQuote($record, $livewire);

                return static::execute($quote);
            });
    }

    public static function execute(Quote $quote): mixed
    {
        try {
            $sale = $quote->createSale(auth()->id());
        } catch (InvalidArgumentException|ValidationException $e) {
            Notification::make()
                ->title('No se pudo crear la venta')
                ->body($e instanceof ValidationException
                    ? (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                    : $e->getMessage())
                ->danger()
                ->send();

            return null;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('No se pudo crear la venta')->danger()->send();

            return null;
        }

        $ordersCount = $sale->productionOrders()->count();
        $body = $ordersCount > 0
            ? "Se vincularon {$ordersCount} orden(es) de producción a la venta."
            : 'Venta creada. Confírmala para emitir factura (sin descontar inventario por ahora).';

        Notification::make()
            ->title("Venta {$sale->code} creada")
            ->body($body)
            ->success()
            ->send();

        return redirect(SaleResource::getUrl('edit', ['record' => $sale]));
    }

    protected static function resolveQuote(mixed $record, mixed $livewire): Quote
    {
        if ($record instanceof Quote) {
            return $record;
        }

        if (is_object($livewire) && method_exists($livewire, 'getRecord')) {
            $fromPage = $livewire->getRecord();

            if ($fromPage instanceof Quote) {
                return $fromPage;
            }
        }

        throw new InvalidArgumentException('No se encontró la cotización.');
    }
}
