<?php

namespace App\Models;

use App\Enums\CustomerIdentityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Customer extends Model
{
    use SoftDeletes;

    /**
     * Campos de texto que siempre se guardan en MAYÚSCULAS (excepto email).
     *
     * @var list<string>
     */
    public const UPPERCASE_FIELDS = [
        'name',
        'company_name',
        'contact_name',
        'tax_id',
        'phone',
        'address',
        'city',
        'notes',
    ];

    protected $fillable = [
        'name',
        'company_name',
        'contact_name',
        'document_type',
        'tax_id',
        'email',
        'phone',
        'address',
        'city',
        'notes',
        'is_active',
        'is_retenedor',
        'retenedor_percent',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_retenedor' => 'boolean',
            'retenedor_percent' => 'decimal:2',
            'document_type' => CustomerIdentityType::class,
        ];
    }

    public function withholdingRate(): float
    {
        if (! $this->is_retenedor) {
            return 0.0;
        }

        return max(0, (float) $this->retenedor_percent);
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->normalizeTextCase();
        });
    }

    /**
     * Alias de negocio: document_number ↔ tax_id (columna existente).
     */
    protected function documentNumber(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->tax_id,
            set: function (?string $value): array {
                return ['tax_id' => $value];
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function isUnassigned(): bool
    {
        return blank($this->user_id);
    }

    public function hasBillingDocument(): bool
    {
        return filled($this->tax_id);
    }

    public function canPlaceOrders(): bool
    {
        return $this->is_active && $this->hasBillingDocument();
    }

    public function displayName(): string
    {
        if (filled($this->company_name) && $this->company_name !== $this->name) {
            return "{$this->name} ({$this->company_name})";
        }

        return (string) $this->name;
    }

    /**
     * Nombre de la persona de contacto (no la razón social / nombre comercial).
     * Para persona natural sin contacto aparte, usa el nombre del cliente.
     */
    public function personContactName(): ?string
    {
        if (filled($this->contact_name)) {
            return (string) $this->contact_name;
        }

        if ($this->document_type === CustomerIdentityType::Nit) {
            return null;
        }

        return filled($this->name) ? (string) $this->name : null;
    }

    /**
     * Impide registrar pedidos si falta el NIT/documento.
     *
     * @throws ValidationException
     */
    public function assertReadyForOrders(): void
    {
        if ($this->hasBillingDocument()) {
            return;
        }

        throw ValidationException::withMessages([
            'customer_id' => "El cliente «{$this->name}» no tiene NIT/documento. Completa su información antes de registrar pedidos.",
        ]);
    }

    /** @param  Builder<Customer>  $query */
    public function scopeReadyForOrders(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('tax_id')
            ->where('tax_id', '!=', '');
    }

    /** @param  Builder<Customer>  $query */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /** @param  Builder<Customer>  $query */
    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Fuerza MAYÚSCULAS en todos los campos de texto excepto el correo.
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

        if (isset($this->attributes['email']) && is_string($this->attributes['email'])) {
            $this->attributes['email'] = trim($this->attributes['email']);
        }
    }
}
