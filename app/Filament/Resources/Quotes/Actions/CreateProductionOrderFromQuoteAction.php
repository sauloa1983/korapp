<?php

namespace App\Filament\Resources\Quotes\Actions;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\Process;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CreateProductionOrderFromQuoteAction
{
    public static function make(): Action
    {
        return Action::make('createProductionOrders')
            ->label('Crear OP')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('warning')
            ->modalHeading('Crear orden de producción')
            ->modalDescription('Flujo: Cotización → OP → Venta. En cotizaciones Serna se crea 1 OP por pieza (o 1 por cotización) con la secuencia de etapas adentro; en catálogo, 1 OP por producto terminado.')
            ->form([
                CheckboxList::make('process_ids')
                    ->label('Procesos de producción')
                    ->options(fn (): array => static::processOptionsInOrder())
                    ->columns(1)
                    ->searchable()
                    ->bulkToggleable()
                    ->required()
                    ->helperText('Orden igual al del menú Procesos. Solo se usan las etapas que marques.'),
            ])
            ->action(function (array $data, mixed $record = null, mixed $livewire = null) {
                $quote = static::resolveQuote($record, $livewire);

                return static::execute($quote, $data);
            });
    }

    public static function execute(Quote $quote, array $data): mixed
    {
        try {
            $orders = $quote->createProductionOrders(auth()->id(), $data['process_ids'] ?? []);
        } catch (InvalidArgumentException|ValidationException $e) {
            Notification::make()
                ->title('No se pudo crear la OP')
                ->body($e instanceof ValidationException
                    ? (collect($e->errors())->flatten()->first() ?: $e->getMessage())
                    : $e->getMessage())
                ->danger()
                ->send();

            return null;
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('No se pudo crear la OP')->danger()->send();

            return null;
        }

        Notification::make()
            ->title($orders->count() === 1
                ? "OP {$orders->first()->code} creada"
                : "{$orders->count()} órdenes de producción creadas")
            ->body('Cuando terminen en planta, genera la venta/factura desde la cotización.')
            ->success()
            ->send();

        if ($orders->count() === 1) {
            return redirect(ProductionOrderResource::getUrl('edit', ['record' => $orders->first()]));
        }

        return redirect(ProductionOrderResource::getUrl('index'));
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

    /**
     * Etapas en el mismo orden del menú Procesos (sort_order). Solo el nombre.
     *
     * @return array<int, string>
     */
    protected static function processOptionsInOrder(): array
    {
        return Process::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Process $process): array => [
                $process->id => $process->name,
            ])
            ->all();
    }
}
