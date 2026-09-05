<?php

namespace App\Filament\Pages;

use App\Models\ProductionLog;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class EscaneoOperario extends Page
{
    protected string $view = 'filament.pages.escaneo-operario';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Producción';

    protected static ?string $title = 'Escaneo de etapas';

    protected static ?string $navigationLabel = 'Escaneo (operario)';

    protected static ?int $navigationSort = 90;

    public ?string $token = null;

    /** Solo super_admin y operarios pueden usar la estación de escaneo. */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->hasRole('Operario')
            || $user->can('View:EscaneoOperario')
            || $user->can('ViewAny:ProductionOrder');
    }

    /** Procesa el token/URL escaneado disparando iniciar o finalizar la etapa. */
    public function procesar(): void
    {
        $token = $this->extractToken($this->token);

        if (blank($token)) {
            Notification::make()->title('Ingresa o escanea un código.')->warning()->send();

            return;
        }

        $log = ProductionLog::query()->where('qr_token', $token)->first();

        if (! $log) {
            Notification::make()->title('Código no reconocido.')->danger()->send();

            return;
        }

        $result = $log->handleScan(Auth::id());
        $log->refresh();

        if ($result === 'sin_cambios') {
            Notification::make()
                ->title('Esta etapa ya estaba finalizada.')
                ->body("{$log->process->name} · Orden {$log->productionOrder->code}")
                ->warning()
                ->send();

            $this->token = null;

            return;
        }

        Notification::make()
            ->title($result === 'iniciada' ? 'Etapa iniciada' : 'Etapa finalizada')
            ->body("{$log->process->name} · Orden {$log->productionOrder->code}")
            ->success()
            ->send();

        $this->token = null;
    }

    /** Últimas etapas registradas por el operario para retroalimentación inmediata. */
    public function getRecentLogs(): Collection
    {
        return ProductionLog::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('started_at')
            ->with(['process', 'productionOrder'])
            ->latest('started_at')
            ->limit(10)
            ->get();
    }

    /** Acepta tanto el token puro como la URL completa del QR. */
    private function extractToken(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Str::contains($value, '/')) {
            return Str::afterLast(rtrim($value, '/'), '/');
        }

        return $value;
    }
}
