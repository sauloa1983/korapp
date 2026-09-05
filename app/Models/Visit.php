<?php

namespace App\Models;

use App\Enums\VisitStatus;
use App\Enums\VisitType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Visit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_id',
        'customer_id',
        'user_id',
        'type',
        'status',
        'subject',
        'scheduled_at',
        'completed_at',
        'next_follow_up_at',
        'notes',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'type' => VisitType::class,
            'status' => VisitStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Visit $visit): void {
            if (blank($visit->lead_id) && blank($visit->customer_id)) {
                throw ValidationException::withMessages([
                    'lead_id' => 'Debes vincular la visita a un prospecto o a un cliente.',
                ]);
            }

            if (blank($visit->user_id) && auth()->id()) {
                $visit->user_id = auth()->id();
            }

            // Si el prospecto ya es cliente, hereda el vínculo.
            if (filled($visit->lead_id) && blank($visit->customer_id)) {
                $leadCustomerId = Lead::query()->whereKey($visit->lead_id)->value('customer_id');

                if (filled($leadCustomerId)) {
                    $visit->customer_id = $leadCustomerId;
                }
            }

            if ($visit->status === VisitStatus::Realizada && blank($visit->completed_at)) {
                $visit->completed_at = now();
            }

            if ($visit->status !== VisitStatus::Realizada) {
                $visit->completed_at = null;
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markCompleted(?string $outcome = null): void
    {
        $this->forceFill([
            'status' => VisitStatus::Realizada,
            'completed_at' => now(),
            'outcome' => $outcome ?? $this->outcome,
        ])->save();
    }

    public function contactName(): string
    {
        return $this->customer?->name
            ?? $this->lead?->name
            ?? 'Sin contacto';
    }

    /** @param  Builder<Visit>  $query */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where('status', VisitStatus::Programada)
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->orderBy('scheduled_at');
    }

    /** @param  Builder<Visit>  $query */
    public function scopeDueFollowUps(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->endOfDay())
            ->where('status', '!=', VisitStatus::Cancelada)
            ->orderBy('next_follow_up_at');
    }
}
