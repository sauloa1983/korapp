<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Services\EInvoice\EInvoiceProvider;
use App\Services\EInvoice\FakeEInvoiceProvider;
use Filament\Actions\CreateAction;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación.
     */
    public function register(): void
    {
        // Resuelve el proveedor de facturación electrónica según la config.
        $this->app->bind(EInvoiceProvider::class, function (): EInvoiceProvider {
            $driver = config('einvoice.default', 'fake');
            $class = config("einvoice.drivers.{$driver}", FakeEInvoiceProvider::class);

            return $this->app->make($class);
        });

        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Inicializa los servicios de la aplicación.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\App::setLocale(config('app.locale', 'es'));
        \Carbon\Carbon::setLocale(config('app.locale', 'es'));

        // Necesario cuando la app vive en subcarpeta (ej. /korapp en cPanel).
        if ($rootUrl = config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(rtrim($rootUrl, '/'));

            if (str_starts_with($rootUrl, 'https://')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

        // Sin botón "Crear y crear otro" en páginas ni modales.
        CreateRecord::disableCreateAnother();
        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action->createAnother(false));

        // Duración de la cookie "Recordarme" (minutos).
        $guard = Auth::guard('web');

        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration((int) config('auth.remember', 60 * 24 * 30));
        }
    }
}
