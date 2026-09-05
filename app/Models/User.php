<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * Campos de texto que siempre se guardan en MAYÚSCULAS (excepto email).
     *
     * @var list<string>
     */
    public const UPPERCASE_FIELDS = [
        'name',
    ];

    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'password',
        'must_change_password',
        'pin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->normalizeTextCase();
        });
    }

    /**
     * Fuerza MAYÚSCULAS en el nombre.
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

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['super_admin', 'Vendedor', 'Operario', 'panel_user']);
    }

    public function isOperario(): bool
    {
        return $this->hasRole('Operario');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** @return list<string> */
    public static function hiddenEmails(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            config('korapp.hidden_user_emails', []),
        )));
    }

    public function scopeVisibleInDirectory(Builder $query): Builder
    {
        foreach (static::hiddenEmails() as $email) {
            $query->whereRaw('LOWER(email) <> ?', [$email]);
        }

        return $query;
    }

    /**
     * Prioriza usuarios con rol Vendedor (aparecen primero en selects).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeOrderSellersFirst(Builder $query): Builder
    {
        $morph = $query->getModel()->getMorphClass();

        return $query->orderByRaw(
            'CASE WHEN EXISTS (
                SELECT 1
                FROM model_has_roles
                INNER JOIN roles ON roles.id = model_has_roles.role_id
                WHERE model_has_roles.model_id = users.id
                  AND model_has_roles.model_type = ?
                  AND roles.name = ?
            ) THEN 0 ELSE 1 END',
            [$morph, 'Vendedor'],
        );
    }

    /** True si aún usa la contraseña inicial o está marcado para forzar el cambio. */
    public function mustChangePasswordBeforeAccess(): bool
    {
        if ((bool) $this->must_change_password) {
            return true;
        }

        $defaultPassword = (string) config('korapp.default_user_password', 'password');

        if ($defaultPassword === '') {
            return false;
        }

        $hash = (string) ($this->getAuthPassword()
            ?: $this->getRawOriginal('password')
            ?: ($this->getAttributes()['password'] ?? ''));

        return $hash !== '' && Hash::check($defaultPassword, $hash);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->avatar_path)) {
            return null;
        }

        return asset('storage/'.$this->avatar_path);
    }

    /** Clientes asignados a este vendedor. */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** Órdenes de producción solicitadas por el usuario. */
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    /** Etapas de producción ejecutadas por el usuario (operario). */
    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }
}
