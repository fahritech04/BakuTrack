<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'source_name',
        'external_id',
        'display_name',
        'city',
        'url',
        'trust_score',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'trust_score' => 'decimal:3',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
