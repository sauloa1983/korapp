<?php

namespace App\Models;

use App\Enums\CustomerDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerDocument extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'title',
        'path',
        'original_name',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerDocumentType::class,
            'expires_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function url(): ?string
    {
        if (blank($this->path)) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }
}
