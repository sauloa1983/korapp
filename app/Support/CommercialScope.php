<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Limita consultas comerciales al vendedor autenticado.
 * Admin y Gerencia ven el consolidado de la empresa.
 */
class CommercialScope
{
    public static function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    public static function seesOnlyOwnData(): bool
    {
        $user = static::user();

        if ($user === null) {
            return true;
        }

        if ($user->isSuperAdmin() || $user->hasRole('Gerencia')) {
            return false;
        }

        return $user->hasRole('Vendedor');
    }

    public static function ownerId(): ?int
    {
        return static::seesOnlyOwnData() ? static::user()?->id : null;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function constrain(Builder $query, string $column = 'user_id'): Builder
    {
        $ownerId = static::ownerId();

        if ($ownerId !== null) {
            $query->where($column, $ownerId);
        }

        return $query;
    }

    /** True si el registro pertenece al vendedor (o si no aplica el filtro). */
    public static function owns(?int $ownerUserId): bool
    {
        if (! static::seesOnlyOwnData()) {
            return true;
        }

        return $ownerUserId !== null && $ownerUserId === static::ownerId();
    }
}
