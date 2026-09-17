<?php

namespace App\Filament\Resources\Quotes\Actions;

use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class AcceptQuoteForProductionAction
{
    public static function make(): Action
    {
        return Action::make('acceptForProduction')
            ->label('Validar y aceptar')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->modalHeading('Validar y aceptar cotización')
            ->modalDescription(function (mixed $record = null, mixed $livewire = null): string|HtmlString {
                try {
                    return static::descriptionFor(static::resolveQuote($record, $livewire));
                } catch (Throwable) {
                    return 'Revisa que la cotización esté completa antes de aceptarla.';
                }
            })
            ->modalSubmitActionLabel('Aceptar cotización')
            ->action(function (mixed $record = null, mixed $livewire = null) {
                $quote = static::resolveQuote($record, $livewire);

                if (! $quote->isReadyForProduction()) {
                    Notification::make()
                        ->title('Cotización incompleta')
                        ->body(implode(' · ', $quote->productionValidationErrors()))
                        ->danger()
                        ->persistent()
                        ->send();

                    return null;
                }

                try {
                    $quote->acceptForProduction();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Cotización incompleta')
                        ->body(collect($e->errors())->flatten()->implode(' · '))
                        ->danger()
                        ->persistent()
                        ->send();

                    return null;
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('No se pudo aceptar')->danger()->send();

                    return null;
                }

                Notification::make()
                    ->title('Cotización aceptada')
                    ->body('Validación OK. Ya puedes crear la orden de producción.')
                    ->success()
                    ->send();

                return null;
            });
    }

    protected static function descriptionFor(Quote $quote): string|HtmlString
    {
        $errors = $quote->productionValidationErrors();

        if ($errors === []) {
            return 'Todo listo. Al aceptar, la cotización queda bloqueada para edición y podrás crear la orden de producción.';
        }

        $items = collect($errors)
            ->map(fn (string $error): string => '<li>'.e($error).'</li>')
            ->implode('');

        return new HtmlString(
            '<p class="mb-2 font-medium text-danger-600">Faltan estos requisitos:</p>'
            .'<ul class="list-disc space-y-1 ps-5 text-sm">'.$items.'</ul>'
            .'<p class="mt-3 text-sm text-gray-500">Corrígelos en la cotización y vuelve a validar.</p>'
        );
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
