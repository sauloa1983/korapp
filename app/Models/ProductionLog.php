<?php

namespace App\Models;

use App\Enums\ProductionLogStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registra el viaje de una orden a través de un proceso.
 * El escaneo del QR dispara el cambio de estado y el cálculo de tiempos.
 */
class ProductionLog extends Model
{
    protected $fillable = [
        'production_order_id',
        'process_id',
        'user_id',
        'qr_token',
        'sequence',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionLogStatus::class,
            'sequence' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductionLog $log): void {
            if (blank($log->qr_token)) {
                $log->qr_token = (string) Str::uuid();
            }

            if (blank($log->status)) {
                $log->status = ProductionLogStatus::EnEspera;
            }
        });
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /** Operario que ejecuta la etapa. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -----------------------------------------------------------------
    // Máquina de estados disparada por el QR
    // -----------------------------------------------------------------

    /**
     * Punto de entrada del escaneo del QR: alterna el estado de la etapa.
     * Devuelve la acción aplicada para la respuesta al operario.
     *
     * @return 'iniciada'|'finalizada'|'sin_cambios'
     */
    public function handleScan(?int $userId = null): string
    {
        return match ($this->status) {
            ProductionLogStatus::EnEspera => $this->start($userId),
            ProductionLogStatus::Procesando => $this->finish($userId),
            ProductionLogStatus::Terminado => 'sin_cambios',
        };
    }

    /** Inicia la etapa: registra el timestamp de inicio. */
    public function start(?int $userId = null): string
    {
        if ($this->status === ProductionLogStatus::Terminado) {
            return 'sin_cambios';
        }

        $this->forceFill([
            'status' => ProductionLogStatus::Procesando,
            'started_at' => $this->started_at ?? now(),
            'user_id' => $userId ?? $this->user_id,
        ])->save();

        $this->productionOrder->markInProgress();

        return 'iniciada';
    }

    /** Finaliza la etapa: registra el fin y calcula la duración exacta. */
    public function finish(?int $userId = null): string
    {
        // Si se escanea el fin sin un inicio previo, se asume inicio inmediato.
        $startedAt = $this->started_at ?? now();
        $endedAt = now();

        $this->forceFill([
            'status' => ProductionLogStatus::Terminado,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $startedAt->diffInSeconds($endedAt),
            'user_id' => $userId ?? $this->user_id,
        ])->save();

        $this->productionOrder->syncStatusFromLogs($userId);

        return 'finalizada';
    }

    // -----------------------------------------------------------------
    // Reportería de tiempos
    // -----------------------------------------------------------------

    /** Duración en minutos (persistida al finalizar, o en vivo si está procesando). */
    protected function durationMinutes(): Attribute
    {
        return Attribute::get(function (): ?float {
            $seconds = $this->resolveSeconds();

            return $seconds === null ? null : round($seconds / 60, 2);
        });
    }

    /** Duración en horas. */
    protected function durationHours(): Attribute
    {
        return Attribute::get(function (): ?float {
            $seconds = $this->resolveSeconds();

            return $seconds === null ? null : round($seconds / 3600, 2);
        });
    }

    /** Duración legible para reportes ("1h 25m"). */
    protected function durationForHumans(): Attribute
    {
        return Attribute::get(function (): ?string {
            $seconds = $this->resolveSeconds();

            if ($seconds === null) {
                return null;
            }

            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);

            return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
        });
    }

    private function resolveSeconds(): ?int
    {
        if ($this->duration_seconds !== null) {
            return (int) $this->duration_seconds;
        }

        if ($this->status === ProductionLogStatus::Procesando && $this->started_at !== null) {
            return $this->started_at->diffInSeconds(now());
        }

        return null;
    }
}
