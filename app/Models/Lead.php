<?php

namespace App\Models;

use App\Enums\LeadStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Lead extends Model
{
    use SoftDeletes;

    /**
     * Campos de texto que siempre se guardan en MAYÚSCULAS (excepto email).
     *
     * @var list<string>
     */
    public const UPPERCASE_FIELDS = [
        'name',
        'company',
    ];

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'stage',
        'value',
        'sort_order',
        'notes',
        'user_id',
        'customer_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'value' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            $lead->normalizeTextCase();
        });

        static::creating(function (Lead $lead): void {
            if ($lead->sort_order === null || $lead->sort_order === 0) {
                $lead->sort_order = (int) static::query()
                    ->where('stage', $lead->stage?->value ?? LeadStage::New->value)
                    ->max('sort_order') + 1;
            }
        });

        // Embudo, formulario o cualquier actualización: Ganado → Cliente.
        static::saved(function (Lead $lead): void {
            if ($lead->stage === LeadStage::Won && blank($lead->customer_id)) {
                $lead->convertToCustomer();
            }
        });
    }

    /**
     * Fuerza MAYÚSCULAS en nombre y empresa.
     */
    public function normalizeTextCase(): void
    {
        foreach (self::UPPERCASE_FIELDS as $field) {
            $value = $this->attributes[$field] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $this->attributes[$field] = mb_strtoupper(trim($value), 'UTF-8');
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function isUnassigned(): bool
    {
        return blank($this->user_id);
    }

    /** @param  Builder<Lead>  $query */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** @param  Builder<Lead>  $query */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    /** Prospectos en seguimiento (aún no ganados ni perdidos). */
    /** @param  Builder<Lead>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [
            LeadStage::Won->value,
            LeadStage::Lost->value,
        ]);
    }

    /** @param  Builder<Lead>  $query */
    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('stage', LeadStage::Won->value)
            ->whereNotNull('customer_id');
    }

    /** @param  Builder<Lead>  $query */
    public function scopeLost(Builder $query): Builder
    {
        return $query->where('stage', LeadStage::Lost->value);
    }

    public function isConverted(): bool
    {
        return $this->stage === LeadStage::Won && filled($this->customer_id);
    }

    public function moveToStage(LeadStage $stage): void
    {
        $this->stage = $stage;
        $this->sort_order = (int) static::query()
            ->where('stage', $stage->value)
            ->max('sort_order') + 1;

        if ($stage === LeadStage::Won && blank($this->converted_at)) {
            $this->converted_at = now();
        }

        $this->save();
    }

    /**
     * Crea o reutiliza un cliente y lo vincula a este prospecto.
     * Idempotente: si ya tiene customer_id, no hace nada nuevo.
     *
     * @return array{customer: Customer, action: 'existing'|'linked'|'created'}
     */
    public function convertToCustomer(): array
    {
        if (filled($this->customer_id)) {
            return [
                'customer' => $this->customer()->firstOrFail(),
                'action' => 'existing',
            ];
        }

        return DB::transaction(function (): array {
            $matched = $this->findMatchingCustomer();
            $action = $matched ? 'linked' : 'created';

            if ($matched) {
                if ($matched->trashed()) {
                    $matched->restore();
                }

                $matched->fill([
                    'name' => $matched->name ?: $this->customerName(),
                    'email' => $matched->email ?: $this->email,
                    'phone' => $matched->phone ?: $this->phone,
                    'notes' => $this->mergeNotes($matched->notes),
                    'is_active' => true,
                    'user_id' => $matched->user_id ?: $this->user_id,
                ])->save();

                $customer = $matched;
            } else {
                $customer = Customer::query()->create([
                    'name' => $this->customerName(),
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'notes' => $this->notes,
                    'is_active' => true,
                    'user_id' => $this->user_id ?? auth()->id(),
                ]);
            }

            $this->forceFill([
                'customer_id' => $customer->id,
                'converted_at' => $this->converted_at ?? now(),
            ])->saveQuietly();

            $this->quotes()
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);

            $this->setRelation('customer', $customer);

            return [
                'customer' => $customer,
                'action' => $action,
            ];
        });
    }

    /** Cliente ya vinculado o coincidencia por correo/teléfono. */
    public function matchingCustomer(): ?Customer
    {
        if (filled($this->customer_id)) {
            return $this->customer;
        }

        return $this->findMatchingCustomer();
    }

    protected function customerName(): string
    {
        return filled($this->company) ? (string) $this->company : (string) $this->name;
    }

    protected function findMatchingCustomer(): ?Customer
    {
        if (filled($this->email)) {
            $byEmail = Customer::withTrashed()
                ->where('email', $this->email)
                ->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        if (filled($this->phone)) {
            return Customer::withTrashed()
                ->where('phone', $this->phone)
                ->first();
        }

        return null;
    }

    protected function mergeNotes(?string $existing): ?string
    {
        if (blank($this->notes)) {
            return $existing;
        }

        if (blank($existing)) {
            return $this->notes;
        }

        if (str_contains($existing, $this->notes)) {
            return $existing;
        }

        return trim($existing."\n\n".$this->notes);
    }
}
