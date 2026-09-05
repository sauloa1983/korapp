<?php

namespace App\Models;

use App\Enums\ProcessDepartment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Catálogo dinámico de etapas de producción (Corte, Grabado, Pulido, etc.).
 * Es un CRUD puro para que el sistema se adapte a cualquier nicho.
 */
class Process extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'department',
        'description',
        'sort_order',
        'estimated_minutes',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department' => ProcessDepartment::class,
            'sort_order' => 'integer',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Process $process): void {
            if (blank($process->slug)) {
                $dept = $process->department instanceof ProcessDepartment
                    ? $process->department->value
                    : (string) ($process->department ?? '');
                $base = trim($dept.'-'.$process->name, '-');
                $process->slug = Str::slug($base !== '' ? $base : (string) $process->name);
            }
        });
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    /** Órdenes de producción que han transitado por este proceso. */
    public function productionOrders(): BelongsToMany
    {
        return $this->belongsToMany(ProductionOrder::class, 'production_logs')
            ->withPivot(['status', 'started_at', 'ended_at', 'duration_seconds'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Ordena por departamento del papel y luego por sort_order. */
    public function scopeOrderedByDepartment(Builder $query): Builder
    {
        $cases = collect(ProcessDepartment::ordered())
            ->map(fn (ProcessDepartment $d): string => "WHEN '{$d->value}' THEN {$d->sortOrder()}")
            ->implode(' ');

        return $query
            ->orderByRaw($cases !== ''
                ? "CASE department {$cases} ELSE 999 END"
                : 'id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
